import argparse
import re
import sys
from datetime import datetime, timedelta, timezone

import mysql.connector
import requests

CONFIG_PHP_PATH = "includes/config.php"


def load_db_config(cli_args):
    """
    Resolve DB credentials. Priority:
    1. CLI args
    2. config.php (parsed via regex)
    3. Environment variables
    """
    import os

    # Start from environment variables as the lowest-priority base
    cfg = {
        "host": os.environ.get("DB_HOST", "localhost"),
        "port": int(os.environ.get("DB_PORT", 3306)),
        "user": os.environ.get("DB_USER", ""),
        "password": os.environ.get("DB_PASSWORD", ""),
        "database": os.environ.get("DB_NAME", ""),
        "prefix": os.environ.get("DB_PREFIX", "uni1_"),
    }

    # Parse config.php
    php_map = {
        "host": r"\$database\['host'\]\s*=\s*'([^']*)'",
        "port": r"\$database\['port'\]\s*=\s*'([^']*)'",
        "user": r"\$database\['user'\]\s*=\s*'([^']*)'",
        "password": r"\$database\['userpw'\]\s*=\s*'([^']*)'",
        "database": r"\$database\['databasename'\]\s*=\s*'([^']*)'",
        "prefix": r"\$database\['tableprefix'\]\s*=\s*'([^']*)'",
    }
    try:
        with open(CONFIG_PHP_PATH, "r") as f:
            php = f.read()
        for key, pattern in php_map.items():
            m = re.search(pattern, php)
            if m:
                cfg[key] = m.group(1)
        cfg["port"] = int(cfg["port"])
    except FileNotFoundError:
        pass  # fall back to env vars

    # CLI args override everything
    if cli_args.db_host:
        cfg["host"] = cli_args.db_host
    if cli_args.db_port:
        cfg["port"] = cli_args.db_port
    if cli_args.db_user:
        cfg["user"] = cli_args.db_user
    if cli_args.db_password:
        cfg["password"] = cli_args.db_password
    if cli_args.db_name:
        cfg["database"] = cli_args.db_name
    if cli_args.db_prefix:
        cfg["prefix"] = cli_args.db_prefix

    return cfg


def connect_db(cfg):
    return mysql.connector.connect(
        host=cfg["host"],
        port=cfg["port"],
        user=cfg["user"],
        password=cfg["password"],
        database=cfg["database"],
    )


def analyze_player(event_times, sleep_seconds):
    """
    Given a sorted list of Unix timestamps, compute the max gap between
    consecutive events and whether the player never rested long enough.

    Returns (max_gap_seconds, is_flagged).
    is_flagged is True when max_gap < sleep_seconds (never took a long-enough break).
    """
    if len(event_times) < 2:
        return (None, False)

    max_gap = 0
    for i in range(1, len(event_times)):
        gap = event_times[i] - event_times[i - 1]
        if gap > max_gap:
            max_gap = gap

    is_flagged = max_gap < sleep_seconds
    return (max_gap, is_flagged)


def fetch_flagged_players(conn, prefix, sleep_seconds, days, min_actions, universe):
    """
    Query fleet dispatches, building queues, and research queues.
    Merge all event timestamps per player and flag those who never take a break.
    """
    universe_filter_fleets   = "AND lf.fleet_universe = %s" if universe is not None else ""
    universe_filter_activity = "AND al.universe = %s"       if universe is not None else ""

    # Build param lists — each sub-query needs its own copy of the days param,
    # plus an optional universe param.
    def sub_params():
        p = [days]
        if universe is not None:
            p.append(universe)
        return p

    query = f"""
        SELECT u.id, u.username, u.universe, ev.event_time, ev.source
        FROM (
            SELECT lf.fleet_owner  AS owner_id,
                   lf.fleet_start_time AS event_time,
                   'fleet' AS source
            FROM {prefix}log_fleets lf
            WHERE lf.fleet_start_time >= UNIX_TIMESTAMP(NOW() - INTERVAL %s DAY)
            {universe_filter_fleets}

            UNION ALL

            SELECT al.owner_id,
                   al.queued_at AS event_time,
                   'building' AS source
            FROM {prefix}log_buildings al
            WHERE al.queued_at >= UNIX_TIMESTAMP(NOW() - INTERVAL %s DAY)
            {universe_filter_activity}

            UNION ALL

            SELECT al.owner_id,
                   al.queued_at AS event_time,
                   'research' AS source
            FROM {prefix}log_research al
            WHERE al.queued_at >= UNIX_TIMESTAMP(NOW() - INTERVAL %s DAY)
            {universe_filter_activity}
        ) ev
        JOIN {prefix}users u ON u.id = ev.owner_id
        WHERE u.bana = 0
          AND u.urlaubs_modus = 0
        ORDER BY u.id, ev.event_time ASC
    """

    params = sub_params() + sub_params() + sub_params()

    cursor = conn.cursor(dictionary=True)
    cursor.execute(query, params)
    rows = cursor.fetchall()
    cursor.close()

    # Group events by player
    players = {}
    for row in rows:
        pid = row["id"]
        if pid not in players:
            players[pid] = {
                "id": pid,
                "username": row["username"],
                "universe": row["universe"],
                "times": [],
                "counts": {"fleet": 0, "building": 0, "research": 0},
            }
        players[pid]["times"].append(int(row["event_time"]))
        players[pid]["counts"][row["source"]] += 1

    window_start = datetime.now(timezone.utc) - timedelta(days=days)
    window_end = datetime.now(timezone.utc)

    flagged = []
    for pid, data in players.items():
        times = sorted(data["times"])
        if len(times) < min_actions:
            continue

        max_gap, is_flagged = analyze_player(times, sleep_seconds)
        if not is_flagged:
            continue

        flagged.append(
            {
                "username": data["username"],
                "universe": data["universe"],
                "total_actions": len(times),
                "counts": data["counts"],
                "max_gap_seconds": max_gap,
                "window_start": window_start,
                "window_end": window_end,
            }
        )

    return flagged


def _fmt_gap(seconds):
    if seconds is None:
        return "n/a"
    h = int(seconds) // 3600
    m = (int(seconds) % 3600) // 60
    return f"{h}h {m:02d}m"


def format_console_report(players):
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M UTC")
    print(f"\n{'='*60}")
    print(f"  Bot Detection Report — {now}")
    print(f"{'='*60}")

    if not players:
        print("  No suspicious players detected.")
        print(f"{'='*60}\n")
        return

    print(f"  {len(players)} suspicious player(s) flagged:\n")
    for p in players:
        ws = p["window_start"].strftime("%Y-%m-%d")
        we = p["window_end"].strftime("%Y-%m-%d")
        c = p["counts"]
        print(f"  Player   : {p['username']}")
        print(f"  Universe : {p['universe']}")
        print(f"  Actions  : {p['total_actions']} total  "
              f"(fleets: {c['fleet']}, buildings: {c['building']}, research: {c['research']})")
        print(f"  Max gap  : {_fmt_gap(p['max_gap_seconds'])} (longest break)")
        print(f"  Window   : {ws} → {we}")
        print(f"  {'-'*40}")

    print(f"{'='*60}\n")


def send_discord_webhook(url, players):
    now_str = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M UTC")

    if not players:
        embed = {
            "title": f"Bot Detection Report — {now_str}",
            "description": "All clear. No suspicious players detected.",
            "color": 0x2ECC71,  # green
            "footer": {"text": "HiveNova Anti-Bot Monitor"},
        }
    else:
        fields = []
        for p in players:
            ws = p["window_start"].strftime("%Y-%m-%d")
            we = p["window_end"].strftime("%Y-%m-%d")
            c = p["counts"]
            value = (
                f"Universe: **{p['universe']}**\n"
                f"Total actions: **{p['total_actions']}** "
                f"(fleets: {c['fleet']}, buildings: {c['building']}, research: {c['research']})\n"
                f"Longest break: **{_fmt_gap(p['max_gap_seconds'])}**\n"
                f"Window: {ws} → {we}"
            )
            fields.append({"name": f":red_circle: {p['username']}", "value": value, "inline": False})

        embed = {
            "title": f"Bot Detection Report — {now_str}",
            "description": f"**{len(players)} suspicious player(s) flagged.**",
            "color": 0xE74C3C,  # red
            "fields": fields,
            "footer": {"text": "HiveNova Anti-Bot Monitor"},
        }

    payload = {"embeds": [embed]}
    resp = requests.post(url, json=payload, timeout=10)
    if not resp.ok:
        print(f"[WARNING] Discord webhook returned {resp.status_code}: {resp.text}", file=sys.stderr)


def main():
    parser = argparse.ArgumentParser(
        description="HiveNova bot detection — flags players who never take breaks between game actions."
    )
    parser.add_argument("--sleep-hours", type=float, default=2.0,
                        help="Minimum break duration (hours) to consider human (default: 2.0)")
    parser.add_argument("--days", type=int, default=7,
                        help="Rolling window in days (default: 7)")
    parser.add_argument("--min-actions", type=int, default=10,
                        help="Minimum total actions required to analyze a player (default: 10)")
    parser.add_argument("--universe", type=int, default=None,
                        help="Limit analysis to a specific universe (default: all)")
    parser.add_argument("--dry-run", action="store_true",
                        help="Print results to console only")
    parser.add_argument("--discord-webhook", metavar="URL", default=None,
                        help="Discord webhook URL for notifications")
    parser.add_argument("--db-host", default=None)
    parser.add_argument("--db-port", type=int, default=None)
    parser.add_argument("--db-user", default=None)
    parser.add_argument("--db-password", default=None)
    parser.add_argument("--db-name", default=None)
    parser.add_argument("--db-prefix", default=None)

    args = parser.parse_args()

    sleep_seconds = args.sleep_hours * 3600

    cfg = load_db_config(args)
    prefix = cfg["prefix"]

    try:
        conn = connect_db(cfg)
    except mysql.connector.Error as e:
        print(f"[ERROR] Could not connect to database: {e}", file=sys.stderr)
        sys.exit(1)

    try:
        flagged = fetch_flagged_players(
            conn, prefix, sleep_seconds, args.days, args.min_actions, args.universe
        )
    finally:
        conn.close()

    # Always print to console when --dry-run or no webhook configured
    if args.dry_run or not args.discord_webhook:
        format_console_report(flagged)

    # Send to Discord if webhook provided (and not dry-run)
    if args.discord_webhook and not args.dry_run:
        send_discord_webhook(args.discord_webhook, flagged)
        action = "all-clear" if not flagged else f"{len(flagged)} flagged"
        print(f"[INFO] Discord notification sent ({action}).")


if __name__ == "__main__":
    main()
