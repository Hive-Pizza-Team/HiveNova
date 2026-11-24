# Releasing HiveNova

How we tag and publish releases. Tags mark **deployed (or about-to-deploy) `master` commits** — not feature branches.

## Versioning

Use [SemVer](https://semver.org/) with a leading `v`:

| Bump | Tag example | When |
|------|-------------|------|
| **MAJOR** | `v2.0.0` | Breaking player/admin behavior, risky schema changes, wipe-era resets |
| **MINOR** | `v1.1.0` | New features (ships, feats, Hive integrations, admin tools) |
| **PATCH** | `v1.0.1` | Bugfixes and small safe improvements only |

Always use **annotated** tags. Never move or reuse a published tag.

Season boundaries may use the normal SemVer bump (often MAJOR or MINOR). Optional extra refs like `season-3-start` are fine for ops, but SemVer remains the release id.

## Prerequisites

- Release from **`master`** only (the commit that is live or next to go live)
- CI green on that commit
- Note any pending `install/migrations/` / `migrate.php` steps in the release body
- `gh` authenticated with repo access

## Product version

The release tag is the product version players and admins see. The commit you tag must already contain:

- `install/VERSION` set to that tag (`v1.0.1`, not the old `2.0` identifier)
- `changelog/vX.Y.Z.md` with the player-facing notes (What’s New reads these files)

`DB_VERSION_REQUIRED` in `includes/dbtables.php` is the schema counter. Do not bump `install/VERSION` for a migration.

Prepare those two files on a branch (does not tag):

```bash
./scripts/release.sh v1.0.1 --prepare
```

`--prepare` writes `install/VERSION` and creates `changelog/vX.Y.Z.md` only when that file is missing. Replace the placeholder bullet, commit, and merge to `master`. Migration 63 widens `config.VERSION` and sets existing rows to `v1.6.0`. Later releases are copied into the database by admin Clear Cache (the daily cron calls it too).

## One-command release

From the repo root on an up-to-date `master`:

```bash
./scripts/release.sh v1.0.1
```

Dry run (no tag push / no GitHub Release):

```bash
./scripts/release.sh v1.0.1 --dry-run
```

What the script does:

1. Refuses to tag unless `install/VERSION` and `changelog/vX.Y.Z.md` match the tag
2. Verifies you are on `master`, clean, and in sync with `origin/master`
3. Creates annotated tag `vX.Y.Z`
4. Pushes the tag
5. Creates a GitHub Release with auto-generated notes from merged PRs

Edit the release notes in the GitHub UI afterward: group **Features / Fixes / Admin / Migrations**, and call out wipe or migration impact. The in-game What’s New text is `changelog/vX.Y.Z.md`, not the GitHub Release body.

## Manual equivalent

Use this only when `install/VERSION` and `changelog/vX.Y.Z.md` already match the tag.

```bash
git checkout master && git pull
git tag -a v1.0.1 -m "v1.0.1: short summary"
git push origin v1.0.1
gh release create v1.0.1 --generate-notes --title "v1.0.1"
```

## Hotfixes

1. Branch from the tag: `git checkout -b hotfix/v1.0.2 v1.0.1`
2. Fix, open PR into `master`, merge
3. Tag the merge commit on `master` as `v1.0.2` (patch)

## Baseline

`v1.0.0` is the first tagged production baseline (commit on `master` as of 2026-08-27). Bump from there for subsequent deploys.

## What not to do

- Do not tag feature branches
- Do not use lightweight tags for releases
- Do not force-push or rewrite published tags
- Do not tag every merged PR — tag deployable units
- Do not push commits directly to `master`; merge via PR, then tag
