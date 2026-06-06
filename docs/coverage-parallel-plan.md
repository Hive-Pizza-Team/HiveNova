# Coverage 80% — parallel subagent plan (replaces orchestrator swarm)

**Status:** approved to try · **Branch:** `feature/coverage-80` · **Baseline:** 56.86% (3804/6690) · **Gap:** ~1548 statements

## Why change approach

The HiveNova orchestrator swarm added too much ceremony for this task:

| Problem | Effect |
|---------|--------|
| Orchestrator subagent crashes (`WritableIterable is closed`) | Waves die mid-run |
| Context limits + Supervisor handoffs | Feels like you must spawn new sessions |
| Parent + orchestrator + php-dev | Three layers; parent still merges/fixes |
| Shared `tests/Support/*` | Parallel workers collide; CI goes red |

**New model:** one **coordinator** (this chat) + **N parallel php-dev subagents** per wave. No orchestrator subagent, no `WAITING_FOR_HUMAN`, no `/loop`.

## Roles

| Role | Does | Does not |
|------|------|----------|
| **You** | Say `run wave`, `stop`, `push now` | Approve every commit |
| **Coordinator** | Ledger → assign paths → launch Tasks → merge → CI → commit | Edit `includes/**` |
| **php-dev Task (×3–5)** | Add tests in assigned paths only | Touch other workers' paths |

`swarm-state.md` is optional; use this file + ledger for progress.

## Wave protocol (every iteration)

```text
1. bash tests/coverage-ledger.sh --top 20
2. Pick 3–5 targets with DISJOINT test paths (see rules below)
3. One message → N parallel Task (generalPurpose, composer-2.5-fast)
   Each prompt: hivenova-php-dev rules + ALLOWED PATHS + MUST NOT TOUCH + phpunit subset
4. Workers return → git diff --stat (conflict check)
5. ./tests/run-ci-local.sh  (fix in coordinator if red; re-dispatch one fix worker if needed)
6. XDEBUG_MODE=coverage phpunit --coverage-clover coverage/clover.xml
7. git commit -m "test(coverage): wave N — …"
8. Repeat until tree ≥80% or you say stop
```

**Commit policy:** local commit after every green wave (same as `auto_continue`). **No push** until ≥80% or `push now`.

## Path ownership rules (critical)

1. **At most one worker** may edit `tests/Support/**` per wave.
2. Workers **never** edit the same `tests/Unit/*.php` file.
3. Prefer **new** `tests/Unit/{Class}Test.php` over expanding many unrelated files.
4. **Never** `unset($GLOBALS['resource'])` / `$reslist` / `$pricelist` in tearDown.
5. **Never** overwrite `pricelist[$id]` without `array_merge` (keep `cost` keys).
6. No live Hive API tests (`isHiveAccountExists('hiveio')`) — use invalid format or missing account only.
7. No probabilistic tests without fixed seed or high attempt cap; drop branches that flake.

## Worker prompt template

```text
You are hivenova-php-dev. Repo: /Users/tor/Code/HiveNova
Read CLAUDE.md. Tests only — do not edit includes/** or styles/**.

Goal: Raise coverage on {CLASS_FILE} ({current %} from ledger)

ALLOWED PATHS:
- tests/Unit/{Specific}Test.php
- (optional) tests/Support/{OneFile}.php  ← only if listed

MUST NOT TOUCH: any other path

Patterns: FakeDatabase + SwapDatabaseInstance, missionFleetFixture, game_data bootstrap.

Acceptance:
- php vendor/bin/phpunit {your files} --no-coverage passes
- Meaningful tests (not empty asserts)

Return: files changed, test count, blockers.
```

## Wave backlog (ledger-driven)

Assign **one class cluster per worker**. Re-run ledger after each wave; numbers shift.

### Wave A — core economy (≈195 stmt gap)

| Worker | Target | Allowed paths |
|--------|--------|----------------|
| A1 | `ResourceUpdate.php` (46%) | `tests/Unit/ResourceUpdateTest.php` only |
| A2 | `StatBuilder.php` (36%) | `tests/Unit/StatBuilderTest.php` only |
| A3 | `BuildFunctions.php` (54%) | `tests/Unit/BuildFunctionsTest.php` only |

### Wave B — zero-coverage UI/data (≈400+ stmt)

| Worker | Target | Allowed paths |
|--------|--------|----------------|
| B1 | `GalaxyRows.php` (0%) | `tests/Unit/GalaxyRowsTest.php` (new), `FakeDatabase.php` **sole Support owner** |
| B2 | `Template.php` (25%) | `tests/Unit/TemplateTest.php` only |
| B3 | `AllianceService.php` (40%) | `tests/Unit/AllianceServiceTest.php` only |

### Wave C — missions tail (≈300 stmt)

| Worker | Target | Allowed paths |
|--------|--------|----------------|
| C1 | `MissionCaseDestruction.php` (65%) | `tests/Unit/MissionCaseDestruction*.php`, `MissionCombatFixtures.php` |
| C2 | `MissionCaseAttack.php` (69%) | `tests/Unit/MissionCaseAttack*.php`, `MissionCombatFixtures.php` — **serialize C1 then C2** OR split combat fixtures: one owner only |
| C3 | `MissionCaseTransport.php` (34%) | `tests/Unit/MissionCaseTransport*.php`, `MissionFleetFixtures.php` |

*Note: C1/C2 share fixtures — run **sequentially** or give **one worker** both mission files.*

### Wave D — services & mop (toward 80%)

| Worker | Target |
|--------|--------|
| D1 | `PushNotificationService.php` |
| D2 | `Database.php` + `DatabaseBC.php` |
| D3 | `cronjob/*`, `extauth/*` (one cron + one extauth per wave) |

### Wave E — caches / hard files (last)

`VarsBuildCache.php`, `SQLDumper.php`, `Theme.php`, `Universe.php` — often need heavier fakes; one file per worker.

## Coordinator checklist after each wave

- [ ] `git diff --stat` — no accidental `includes/` changes
- [ ] `./tests/run-ci-local.sh` green
- [ ] `bash tests/check-tree-coverage.sh` — record % in commit message
- [ ] No untracked WIP left uncommitted

## What we stop doing

- `hivenova-orchestrator` subagent for coverage (Mode A)
- `/loop` wake timers
- Session-ending Supervisor reports between waves
- `human_gates_waiting` for commits on this branch

## Success criteria

- Tree coverage ≥ **80%** on `includes/classes/` (unit clover)
- `./tests/run-ci-local.sh` green
- Single PR from `feature/coverage-80` when you say `push now`

## Start command

```text
run coverage wave A — 3 parallel php-dev, commit locally, no push
```
