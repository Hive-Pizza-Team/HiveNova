# HiveNova - Space empire building browser game

[![CI](https://github.com/Hive-Pizza-Team/HiveNova/actions/workflows/ci.yaml/badge.svg)](https://github.com/Hive-Pizza-Team/HiveNova/actions/workflows/ci.yaml)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)](https://php.net)
[![GitHub stars](https://img.shields.io/github/stars/Hive-Pizza-Team/HiveNova?style=social)](https://github.com/Hive-Pizza-Team/HiveNova/stargazers)
[![GitHub forks](https://img.shields.io/github/forks/Hive-Pizza-Team/HiveNova?style=social)](https://github.com/Hive-Pizza-Team/HiveNova/network/members)
[![GitHub issues](https://img.shields.io/github/issues/Hive-Pizza-Team/HiveNova)](https://github.com/Hive-Pizza-Team/HiveNova/issues)
[![GitHub last commit](https://img.shields.io/github/last-commit/Hive-Pizza-Team/HiveNova)](https://github.com/Hive-Pizza-Team/HiveNova/commits)
[![Discord](https://img.shields.io/badge/Discord-Join-5865F2?logo=discord&logoColor=white)](https://discord.gg/BWqmGbtuDn)
[![Powered By Hive](https://img.shields.io/static/v1?label=Hive&message=hive.io&color=E31337&labelColor=212529&logo=hive_blockchain&logoColor=white&style=flat)](https://hive.io/)
---

![MOON_Discord_Event_Banner](https://github.com/user-attachments/assets/96607107-b195-4164-9537-241430acc86e)

Play the game at https://moon.hive.pizza

---

## The Game

The open-source game framework is based on [2Moons](https://gitter.im/2MoonsGame/Lobby/).

Code is located at [https://github.com/Hive-Pizza-Team/HiveNova](https://github.com/Hive-Pizza-Team/HiveNova) repository. It is a fork of [jkroepke/2Moons](https://github.com/jkroepke/2Moons) and SteemNova 2 (https://github.com/steemnova/steemnova) for Hive community purposes. HiveNova repository is the core of the game code.

![badge_powered-by-hive_dark](https://github.com/user-attachments/assets/803e396c-f165-40de-936c-03dd624153ad)

## Tech Stack

- **PHP 8.3**, **Smarty 4.5** templates, **MySQL** (utf8mb4 charset), **PDO**
- Hive blockchain integration via [`mahdiyari/hive-php`](https://github.com/mahdiyari/hive-php)
- **PHPMailer**, **Google reCAPTCHA**, **Parsedown**
- Testing: **PHPUnit 10.5** (`./vendor/bin/phpunit`)

## Architecture Overview

**Entry points** — each bootstraps via `includes/common.php`:

| File | MODE constant | Purpose |
|------|--------------|---------|
| `index.php` | `LOGIN` | Login, registration, landing |
| `game.php` | `INGAME` | All in-game pages |
| `admin.php` | `ADMIN` | Administration panel |
| `cronjob.php` | `CRON` | Background cron jobs |

**Page routing** — pages are loaded dynamically:
- Game pages: `includes/pages/game/Show{Name}Page.class.php`
- Admin pages: `includes/pages/adm/{Name}.php`

**Templates** — Smarty engine; source templates in `styles/templates/`, compiled cache in `cache/`.
Four themes: `hive` (default), `nova`, `gow`, `EpicBlueXIII`.

**Database** — `Database` class (PDO wrapper). Table name constants defined in `includes/dbtables.php`.

**Authority levels**: `AUTH_USR=0`, `AUTH_MOD=1`, `AUTH_OPS=2`, `AUTH_ADM=3`

**Configuration**:
- `includes/constants.php` — 240+ game constants
- `includes/config.php` — DB credentials; created by the web installer, not in git

**Cron job system** — classes in `includes/classes/cronjob/`, one class per job, implementing `CronjobTask`. Jobs are registered in the `uni1_cronjobs` DB table.

## Repository Structure

- `cache/` — temporary compiled Smarty templates
- `chat/` — AJAX in-game client-side chat
- `includes/`
  - game engine, configuration, administration
  - database schema (`dbtables.php`, `constants.php`)
  - external libraries
  - page controllers (`includes/pages/`)
  - cron job classes (`includes/classes/cronjob/`)
- `install/` — web installer, DB creation, migration SQL files
- `language/` — translations: DE, EN, ES, FR, PL, PT, RU, TR
- `licenses/` — open-source license files
- `scripts/` — client-side JavaScript
- `styles/` — CSS, Smarty `.tpl` templates, fonts, images
- `tests/` — PHPUnit test suite

## Key Development Patterns

**Add a game page:**
Create `includes/pages/game/Show{Name}Page.class.php` extending `AbstractPage`.

**Add an admin page:**
Create `includes/pages/adm/{Name}.php`.

**Add a cron job:**
Create a class in `includes/classes/cronjob/` implementing `CronjobTask`, then register it in the `uni1_cronjobs` DB table.

**DB migrations:**
Add `install/migrations/migration_N.sql` and bump `install/VERSION`.

## Local Installation

- Clone the repo
- Install components: `apt install apache2 php8.3 php8.3-gd php8.3-fpm php8.3-mysql php8.3-curl php-ds libapache2-mod mysql-server`
- Install PHP dependencies: `composer install`
- Set php.ini config value: `pdo_mysql.default_charset = utf8mb4`
- Setup mysql: `create user USER identified by PASSWORD; create database DB; grant all privileges on DB.* to USER;`
- Set write privileges to dirs: `cache/`, `includes/`
- Run wizard: `127.0.0.1/install/install.php`

For quick local development without Apache/NGINX, you can use PHP's built-in server:

```bash
php -S localhost:8000
```

### Testing

Run the full CI pipeline locally before pushing:

```bash
./tests/run-ci-local.sh               # unit tests + language check + smoke test
./tests/run-ci-local.sh --integration # also run integration tests (requires MySQL)
```

This mirrors what GitHub Actions runs, including checking that `includes/error.log` is empty after the smoke test. Requires the local dev server to be running on `:8000`.

Individual test commands:

```bash
php vendor/bin/phpunit                  # unit tests
php tests/smoke.php                     # smoke test (local dev defaults)
php .github/scripts/check-language-files.php  # language key validation
php tests/smoke.php https://staging.moon.hive.pizza admin s3cr3t  # remote host
```

### CI

GitHub Actions runs on every push and pull request (`.github/workflows/ci.yaml`):

- **language-check** — validates that all language files are syntactically valid PHP and that every key present in the English reference file exists in each translation
- **test** — runs the PHPUnit unit test suite on PHP 8.3 with xdebug coverage; generates a Clover coverage report
- **smoke** — spins up a MySQL 8 service container, installs the game via `tests/ci-install.php`, starts the PHP built-in server, then runs `tests/smoke.php` which logs in as the test admin and issues an HTTP request to every game page, failing on any PHP error or unexpected redirect

### Database Migrations

After pulling new code, apply any pending schema changes with the CLI migration tool:

```bash
# Check current version and list pending migrations
php migrate.php status

# Apply all pending migrations
php migrate.php run

# Preview SQL that would be executed (no changes made)
php migrate.php run --dry-run
```

Run from the project root. The tool requires `includes/config.php` to exist (created by the web installer).

### If you run HiveNova on NGINX - Read nginx.md file!

## Screenshots

![screenshot](https://github.com/user-attachments/assets/3705e3c5-540c-4915-9f1b-8d4e2c6142ae)

## Copyright and License

HiveNova is a fork of the Open Source Game Framework [jkroepke/2Moons](https://github.com/jkroepke/2Moons) framework.
Background image created by [@mkdrwal](https://hive.blog/@mkdrwal)

HiveNova relies on the Ogame Probabilistic Battle Engine [(OPBE)](https://github.com/jstar88/opbe).

* 2Moons code copyright 2009-2016 Jan-Otto Kröpke released under the MIT License.
* OPBE code copyright 2013 Jstar released under the AGPLv3 License.
* Code copyright 2018 @steemnova released under the MIT License.
* Code copyright 2018-2020 @IntinteDAO released under the MIT License.
* Code copyright 07.05.2020-2020 @IntinteDAO released under the AGPLv3 License
* Code copyright 2025 @TeamMithril released under the AGPLv3 License
