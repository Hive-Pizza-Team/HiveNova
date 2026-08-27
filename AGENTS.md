# HiveNova — Agent Instructions

## Session Start

At the start of every session in this workspace, read these in order before doing any work:

1. `README.md` — project structure, tech stack, install, CI
2. `docs/architecture.md` — globals, element ID constants, service/layer conventions, coverage gate
3. `docs/domain-map.md` — where feature logic lives (when the task touches game systems)
4. `docs/testing.md` — PHPUnit fixtures / fakes (when adding or changing tests)

## Git and branches

- **Never push directly to `master` or `develop`.** Use a feature branch and open a **pull request** to request merging into those branches instead.

## Code Intelligence

- **PHP LSP is available** — use the `LSP` tool for go-to-definition, find-references, hover, document symbols, and call hierarchy on PHP files. Prefer this over grepping for symbol lookups.

## Coding conventions (summary)

Full detail is in `docs/architecture.md`. Short version:

- Prefer `FLEET_MISSION_*`, `SHIP_*`, and `RESOURCE_*` over magic integers in new/touched PHP.
- Put new business logic in `includes/classes/*Service.php` (or repositories), not fat page controllers — PR diff-coverage only gates `includes/classes/`.
- Do not add new `global $USER` / `$PLANET` in classes; pass state explicitly.
- Do not grow `includes/GeneralFunctions.php`; extract instead.
- English language keys are the source of truth; add with `php .github/scripts/add-language-key.php <FILE> <key> <text>` (mirrors all locales).

## Touch checklist (extract-on-touch)

When editing an existing file, prefer small naming/extraction in the same hunk over a repo-wide cleanup:

- If you touch a mission or resource/ship ID conditional, switch that site to `FLEET_MISSION_*` / `RESOURCE_*` / `SHIP_*`.
- If you add non-trivial branching to a `Show*Page`, extract it into a `*Service` under `includes/classes/` and cover it with a unit test.
- Leave unrelated magic integers and god-object neighbors alone in the same PR.
