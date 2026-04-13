# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

HiveNova is an open-source space empire browser game (PHP/MySQL), forked from 2Moons, with Hive blockchain integration. Live at https://moon.hive.pizza.

**Stack**: PHP 8.3, Smarty 4.5 templates, MySQL (utf8mb4), PDO, Composer

## Commands

```bash
# Dependencies
composer install

# Run full local CI (unit tests + language check + CSS check + smoke test)
./tests/run-ci-local.sh

# Run full local CI including integration tests (requires MySQL with game installed)
./tests/run-ci-local.sh --integration

# Run unit tests only
php vendor/bin/phpunit

# Run a single test class or method
php vendor/bin/phpunit --filter BuildFunctionsTest
php vendor/bin/phpunit --filter "BuildFunctionsTest::testSomething"

# Run integration tests only (requires MySQL — run php tests/ci-install.php first)
php vendor/bin/phpunit --configuration phpunit-integration.xml

# Check PHP modernization opportunities (don't auto-apply without reviewing)
./vendor/bin/rector --dry-run

# Validate language files (all keys present, valid PHP syntax)
php .github/scripts/check-language-files.php

# Check CSS design tokens (no named colors allowed)
bash tests/check-css.sh

# Smoke test (local dev server must be running on :8000)
php tests/smoke.php

# Database migrations
php migrate.php status
php migrate.php run
php migrate.php run --dry-run

# Local dev server
php -S localhost:8000
```

**CI** runs on PHP 8.3 and checks: language file validation, CSS design tokens, PHPUnit unit tests (with xdebug coverage), and a smoke test that installs the game into MySQL, starts the dev server, and hits every game page. After the smoke test, `includes/error.log` must be empty (excluding vendor errors) — any PHP error or unexpected redirect fails CI.

## Architecture

### Entry Points

All requests flow through one of four entry points, each setting a `MODE` constant before calling `includes/common.php`:

| File | MODE | Purpose |
|------|------|---------|
| `index.php` | `LOGIN` | Registration, login, password reset |
| `game.php` | `INGAME` | All in-game pages |
| `admin.php` | `ADMIN` | Admin panel (also sets `DATABASE_VERSION='OLD'`) |
| `cronjob.php` | `CRON` | Background jobs (outputs 1×1 transparent GIF) |

### Bootstrap (`includes/common.php`)

Loaded by every entry point. Sets up database, session, user, language, config, and error handling. Sets `APP_ENV=development` to show errors.

### Page Routing

**Game pages** (`INGAME` mode): dynamically loads `includes/pages/game/Show{PageName}Page.class.php`. These extend `AbstractGamePage` and set `protected static $requireModule = MODULE_X` to gate access by enabled universe modules. The `show()` method renders a Smarty template.

**Admin pages** (`ADMIN` mode): dynamically loads `includes/pages/adm/{PageName}.php` — different naming convention, no `Show` prefix or `Page` suffix.

### Namespaces

All classes under `includes/classes/` use PHP namespaces (migrated in 2025):

| Namespace | Directory |
|-----------|-----------|
| `HiveNova\Core` | `includes/classes/` (core classes) |
| `HiveNova\Core\Cache` | `includes/classes/cache/builder/` |
| `HiveNova\Cronjob` | `includes/classes/cronjob/` |
| `HiveNova\Mission` | `includes/classes/missions/` |
| `HiveNova\Repository` | `includes/classes/repository/` |
| `HiveNova\Auth` | `includes/classes/extauth/` |

Page controllers and legacy includes do **not** use namespaces — only `includes/classes/` does.

### Key Classes

- **`Database`** (`HiveNova\Core`): PDO singleton. Table names come from `includes/dbtables.php` via `%%TABLE_ALIAS%%` substitution. Use only parameterized queries; two unsafe methods (`unsafeQuery`, `unsafeSelect`) are marked `@internal` for migrations/schema inspection only.
- **`Config`** (`HiveNova\Core`): Universe/global settings stored in DB.
- **`BuildFunctions`** (`HiveNova\Core`): Building costs, bonuses (attack, defense, shield, build time), resource production formulas.
- **`FleetFunctions`** (`HiveNova\Core`) + `HiveNova\Mission`: Fleet mechanics; 14+ mission types implement `Mission` interface.
- **`ResourceUpdate`** (`HiveNova\Core`): Recalculates resource production on planet load.
- **`Cronjob`** + `HiveNova\Cronjob`: 12 cron job classes implement `CronjobTask` interface; registered in the `uni1_cronjobs` DB table.
- **Repositories** (`HiveNova\Repository`): `UserRepository`, `PlanetRepository`, `MessageRepository`, `BuddyRepository` — thin DB query wrappers for their respective entities.

### Constants (`includes/constants.php`)

240+ constants covering MODULE IDs, AUTH levels (`AUTH_USR=0`, `AUTH_MOD=1`, `AUTH_OPS=2`, `AUTH_ADM=3`), element/building flags, and game rules. This is the canonical reference for module names and permission levels.

### Templates

Smarty 4.5 templates in `styles/templates/`. Four themes: `hive`, `nova`, `gow`, `EpicBlueXIII`. Custom modifiers in `includes/smarty-plugins/`. Compiled to `cache/`.

### CSS Design Tokens

CSS uses a design token system (`var(--color-*)` custom properties). **Named CSS colors are forbidden** in `styles/resource/css/ingame/main.css` and all theme `formate.css` files — use hex values or `var(--color-*)` tokens instead. The `tests/check-css.sh` script enforces this and runs in CI.

### Database Migrations

Migration SQL files live in `install/migrations/migration_N.sql`. The current schema version is tracked in `uni1_system.dbVersion` (currently version 13). Add new migrations by incrementing this file sequence.

### Languages

8 supported languages (DE, EN, ES, FR, PL, PT, RU, TR) in `language/{lang}/`. Each language has files: `INGAME.php`, `TECH.php`, `FLEET.php`, `ADMIN.php`, `INSTALL.php`, `PUBLIC.php`, `L18N.php`, `CUSTOM.php`. The CI validates that every key present in the English reference file exists in each translation.

## Development Patterns

**Add a game page**: Create `includes/pages/game/Show{Name}Page.class.php` extending `AbstractGamePage`, implement `show()`, and create the corresponding template in `styles/templates/game/`.

**Add an admin page**: Create `includes/pages/adm/{Name}.php` (no `Show` prefix, no `Page` suffix).

**Add a cron job**: Implement `CronjobTask` in `includes/classes/cronjob/` under namespace `HiveNova\Cronjob`, then register in `uni1_cronjobs`.

**Add a DB migration**: Create `install/migrations/migration_N.sql`, increment `install/VERSION`, run `php migrate.php run`.

**Add a language key**: Add it to all 8 language files. The CI will fail if any translation is missing a key that exists in EN.

## Session Start Checklist

**The lead developer is very active and merges PRs frequently. Always run this at the start of every session before touching any files:**

```bash
git fetch origin

# What's on main that this branch doesn't have yet?
git log --oneline HEAD..origin/master

# What does this branch have that main doesn't?
git log --oneline origin/master..HEAD
```

If the first command returns commits, **stop and rebase before starting work**:

```bash
git rebase origin/master
```

If the second command shows commits that are already on main (e.g. a PR was merged), the branch is stale — start a fresh branch from main instead:

```bash
git checkout master && git pull && git checkout -b your-new-branch
```

Working on a stale branch means your changes apply to outdated files. When the rebase eventually happens, you get conflicts on lines that didn't need to conflict — and you may silently undo improvements that landed on main after your branch was cut.

## Configuration

`includes/config.php` holds DB credentials (created by installer, not in git). See `includes/config.sample.php` for the structure (host, port, user, userpw, databasename, tableprefix, salt).

**Required PHP extensions**: gmp, gd, pdo_mysql, curl, mbstring
