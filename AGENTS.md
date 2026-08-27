# HiveNova — Agent Instructions

## Session Start

At the start of every session in this workspace, read these in order before doing any work:

1. `README.md` — project structure, tech stack, install, CI
2. `docs/architecture.md` — globals, element ID constants, service/layer conventions, coverage gate

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
- English language keys are the source of truth; mirror keys into all locales for CI.
