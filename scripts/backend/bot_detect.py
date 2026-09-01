import argparse
import re
import sys
from datetime import datetime, timezone

import mysql.connector
import requests

CONFIG_PHP_PATH = "includes/config.php"
NPC_BOT_EMAIL = "bot"
AUTH_USR = 0


def load_db_config(cli_args):
    """
    Resolve DB credentials. Priority:
    1. CLI args
    2. config.php (parsed via regex)
    3. Environment variables
    """
    import os

    cfg = {
        "host": os.environ.get("DB_HOST", "localhost"),
        "port": int(os.environ.get("DB_PORT", 3306)),
        "user": os.environ.get("DB_USER", ""),
        "password": os.environ.get("DB_PASSWORD", ""),
        "database": os.environ.get("DB_NAME", ""),
        "prefix": os.environ.get("DB_PREFIX", "uni1_"),
    }

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
        pass

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


def fetch_flagged_players(conn, prefix, sleep_seconds, days, min_actions, universe):
    now_ts = int(datetime.now(timezone.utc).timestamp())
    cutoff_ts = now_ts - (days * 86400)

    universe_filter_fleets = "AND lf.fleet_universe = %s" if universe is not None else ""
    universe_filter_activity = "AND al.universe = %s" if universe is not None else ""

    def sub_params():
        p = [cutoff_ts]
        if universe is not None:
            p.append(universe)
        return p

    query = f"""
        WITH events AS (
            SELECT owner_id AS user_id, event_time, source FROM (
                SELECT lf.fleet_owner AS owner_id,
                       lf.fleet_start_time AS event_time,
                       'fleet' AS source
                FROM {prefix}log_fleets lf
                WHERE lf.fleet_start_time >= %s
                {universe_filter_fleets}

                UNION ALL

                SELECT al.owner_id,
                       al.queued_at AS event_time,
                       'building' AS source
                FROM {prefix}log_buildings al
                WHERE al.queued_at >= %s
                {universe_filter_activity}

                UNION ALL

                SELECT al.owner_id,
                       al.queued_at AS event_time,
                       'research' AS source
                FROM {prefix}log_research al
                WHERE al.queued_at >= %s
                {universe_filter_activity}

                UNION ALL

                SELECT al.owner_id,
                       al.queued_at AS event_time,
                       'shipyard' AS source
                FROM {prefix}log_shipyard al
                WHERE al.queued_at >= %s
                {universe_filter_activity}
            ) raw
        ),
        ordered AS (
            SELECT user_id, event_time, source,
                   LAG(event_time) OVER (PARTITION BY user_id ORDER BY event_time) AS prev_time
            FROM events
        ),
        gaps AS (
            SELECT user_id,
                   MAX(event_time - prev_time) AS max_internal_gap,
                   MIN(event_time) AS first_time,
                   MAX(event_time) AS last_time,
                   COUNT(*) AS action_count,
                   SUM(CASE WHEN source = 'fleet' THEN 1 ELSE 0 END) AS fleet_count,
                   SUM(CASE WHEN source = 'building' THEN 1 ELSE 0 END) AS building_count,
                   SUM(CASE WHEN source = 'research' THEN 1 ELSE 0 END) AS research_count,
                   SUM(CASE WHEN source = 'shipyard' THEN 1 ELSE 0 END) AS shipyard_count
            FROM ordered
            GROUP BY user_id
            HAVING action_count >= %s
        )
        SELECT u.id, u.username, u.universe,
               GREATEST(
                 COALESCE(g.max_internal_gap, 0),
                 g.first_time - %s,
                 %s - g.last_time
               ) AS max_gap_seconds,
               g.action_count AS total_actions,
               g.fleet_count,
               g.building_count,
               g.research_count,
               g.shipyard_count
        FROM gaps g
        JOIN {prefix}users u ON u.id = g.user_id
        WHERE u.bana = 0
          AND u.urlaubs_modus = 0
          AND u.email != %s
          AND u.authlevel = %s
          AND GREATEST(
                 COALESCE(g.max_internal_gap, 0),
                 g.first_time - %s,
                 %s - g.last_time
               ) < %s
        ORDER BY max_gap_seconds ASC
    """

    params = (
        sub_params()
        + sub_params()
        + sub_params()
        + sub_params()
        + [min_actions, cutoff_ts, now_ts, NPC_BOT_EMAIL, AUTH_USR, cutoff_ts, now_ts, sleep_seconds]
    )

    cursor = conn.cursor(dictionary=True)
    cursor.execute(query, params)
    rows = cursor.fetchall()
    cursor.close()

    window_start = datetime.fromtimestamp(cutoff_ts, tz=timezone.utc)
    window_end = datetime.fromtimestamp(now_ts, tz=timezone.utc)

    flagged = []
    for row in rows:
        flagged.append(
            {
                "username": row["username"],
                "universe": row["universe"],
                "total_actions": int(row["total_actions"]),
                "counts": {
                    "fleet": int(row["fleet_count"]),
                    "building": int(row["building_count"]),
                    "research": int(row["research_count"]),
                    "shipyard": int(row["shipyard_count"]),
                },
                "max_gap_seconds": int(row["max_gap_seconds"]),
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
              f"(fleets: {c['fleet']}, buildings: {c['building']}, "
              f"research: {c['research']}, shipyard: {c['shipyard']})")
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
            "color": 0x2ECC71,
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
                f"(fleets: {c['fleet']}, buildings: {c['building']}, "
                f"research: {c['research']}, shipyard: {c['shipyard']})\n"
                f"Longest break: **{_fmt_gap(p['max_gap_seconds'])}**\n"
                f"Window: {ws} → {we}"
            )
            fields.append({"name": f":red_circle: {p['username']}", "value": value, "inline": False})

        embed = {
            "title": f"Bot Detection Report — {now_str}",
            "description": f"**{len(players)} suspicious player(s) flagged.**",
            "color": 0xE74C3C,
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

    sleep_seconds = int(args.sleep_hours * 3600)

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

    if args.dry_run or not args.discord_webhook:
        format_console_report(flagged)

    if args.discord_webhook and not args.dry_run:
        send_discord_webhook(args.discord_webhook, flagged)
        action = "all-clear" if not flagged else f"{len(flagged)} flagged"
        print(f"[INFO] Discord notification sent ({action}).")


if __name__ == "__main__":
    main()
