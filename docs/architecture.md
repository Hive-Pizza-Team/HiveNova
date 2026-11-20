# HiveNova architecture conventions

Short reference for humans and agents. Stack, install, and CI live in `README.md`; this file is the **how we change the code** map.

## Request globals

Boot (`includes/common.php` → `includes/vars.php`) still exposes ambient game state:

| Global | Meaning |
|--------|---------|
| `$USER` / `$PLANET` | Current player and active planet (mutated by economy/fleet code) |
| `$resource` | Element ID → DB column name (e.g. `RESOURCE_METAL` → `metal`) |
| `$reslist` | Category → list of element IDs (`fleet`, `build`, `ressources`, …) |
| `$pricelist` / `$CombatCaps` / `$requirements` | Cached vars from DB |
| `$LNG` | Loaded language strings |
| `$db` / `Database::get()` | PDO wrapper |

**Convention:** do not add new `global $USER` / `$PLANET` in classes. Prefer constructor args or method parameters (see `GalaxyRows`, `ResourceUpdate::setData` / `setResourceData`). Pages may still read globals; push logic into services that take explicit arrays.

## Element ID ranges

| Range | Kind |
|-------|------|
| 1–99 | Buildings |
| 101–199 | Research |
| 201–399 | Ships |
| 401–599 | Defense |
| 601–699 | Officers |
| 701–799 | Bonuses |
| 801–899 | Race |
| 901–949 | Planet resources (metal / crystal / deuterium / energy) |
| 921 | Pizzabits (schema/column still `darkmatter`) |

Bitmask flags (`ELEMENT_BUILD`, `ELEMENT_FLEET`, …) describe capabilities in `$pricelist`; they are not a substitute for named IDs in conditionals.

## Named ID constants

Defined in `includes/constants.php` (loaded before `vars.php`):

| Prefix | Use |
|--------|-----|
| `FLEET_MISSION_*` | `fleet_mission` values (attack, recycle, expedition, salvage, …) |
| `SHIP_*` | Ship element IDs |
| `RESOURCE_*` | Resource element IDs (`RESOURCE_METAL` … `RESOURCE_DARKMATTER`) |

**Aliases (prefer these in new code):**

- `SHIP_BATTLE_RECYCLER` ≡ `SHIP_PATHFINDER` (219) — TECH name is Battle Recycler
- `SHIP_PIZZABITS_COLLECTOR` ≡ `SHIP_DARK_MATTER` (220) — TECH name is Pizzabits Collector

**Convention:** new and touched conditionals use these constants, not bare integers. Leave unrelated call sites alone in the same PR unless the file is already under edit. Templates/JS may still see numeric IDs; pass named values from PHP when practical.

## Where logic should live

| Layer | Role |
|-------|------|
| `includes/pages/game/Show*Page.php` | HTTP, assign Smarty vars, thin orchestration |
| `includes/classes/*Service.php` | Testable business rules |
| `includes/classes/repository/*` | Reused queries for high-churn tables |
| `includes/classes/missions/*` | Fleet mission handlers |
| `includes/classes/cronjob/*` | Cron tasks (`CronjobTask`) |
| `includes/GeneralFunctions.php` | Legacy free functions — **do not grow**; extract instead |

Trajectory: carve god-objects (`FleetFunctions`, `ResourceUpdate`, `ShowAlliancePage`, combat cases) into services the same way as `AllianceDiplomacyService`, `FleetMissionAvailability`, `FleetPlanetDeduction`, `FleetDispatchService`.

## Database

- Prefer `Database::get()` with `%%TABLE%%` placeholders from `includes/dbtables.php`.
- Grow repositories for fleets / alliance / messages when touching those paths; one-off admin SQL can stay inline.
- Schema changes: `install/migrations/migration_N.sql` + bump `install/VERSION`; apply with `php migrate.php`.

## Templates and themes

- Shared Smarty templates: `styles/templates/game|adm|login/`.
- Skins: `styles/theme/{hive,nova,gow,EpicBlueXIII}/`. Default is `hive` (`DEFAULT_THEME`).
- Prefer fixing `hive` (and shared templates) first; treat other themes as best-effort unless the bug is theme-specific.

## Language

- English under `language/en/` is the source of truth.
- CI (`check-language-files.php`) requires every EN key to exist in DE/ES/FR/PL/PT/RU/TR — it does not check translation quality.
- Add new strings with `php .github/scripts/add-language-key.php <FILE.php> <key> <english text>` (stubs all locales; EN text is an acceptable interim translation).

## JavaScript

- Game scripts live in `scripts/game/` as globals (no bundler required for gameplay).
- Prefer small pure helpers with `tests/js/` coverage when extracting from large files (`overview-planet.js`, `base.js`, `flotten.js`).

## Testing and coverage gate

- Unit: `php vendor/bin/phpunit`
- Static analysis: `composer phpstan` (level 0 on `includes/classes/`; baseline in `phpstan-baseline.neon`)
- Fixture / FakeDatabase cheat sheet: **`docs/testing.md`**
- Local CI mirror: `./tests/run-ci-local.sh`
- **Diff coverage gate** (PRs): ≥80% of changed lines under `includes/classes/` must be covered.
- Page controllers, templates, and language files are **not** gated — put new behavior in classes/services when you want the gate to protect it.

## Agent session checklist

1. Read `README.md`, then this file, then `docs/domain-map.md` when navigating feature code.
2. Prefer PHP LSP for symbol navigation.
3. Never push straight to `master` / `develop` — feature branch + PR.
4. Match nearby style; do not drive-by refactor unrelated files.
5. When naming IDs or extracting services, follow the tables above and the touch checklist in `AGENTS.md`.
