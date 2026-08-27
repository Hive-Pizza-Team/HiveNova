# Unit testing cheat sheet

How agents should set up PHPUnit tests without reinventing bootstrap.

## Commands

```bash
php vendor/bin/phpunit                              # all unit tests
php vendor/bin/phpunit tests/Unit/SomeTest.php      # one file
./tests/run-ci-local.sh                             # mirrors CI (needs :8000 for smoke)
```

PR **diff coverage** (≥80%) applies only to changed lines under `includes/classes/`. Put new logic there when you want the gate to protect it.

## Bootstrap (`tests/bootstrap.php`)

Loaded automatically via `phpunit.xml`. It:

- Registers Composer + PSR-4 for `HiveNova\Core\`, missions, and game pages
- Defines `ROOT_PATH`, `CACHE_PATH`, `MODE=INSTALL`, timestamps, module IDs
- Requires `includes/GeneralFunctions.php`
- Defines fleet/mission/ship/**resource** constants if missing (so tests work without full `constants.php`)
- Loads sparse game data from `tests/fixtures/game_data.php` into **`$GLOBALS['resource']`, `$GLOBALS['reslist']`, `$GLOBALS['pricelist']`, …**

Use `$GLOBALS['resource']` (not a bare `$resource` local) when a test mutates maps — PHPUnit bootstrap scope does not populate true globals the way `vars.php` does in a web request.

## Game-data fixture (`tests/fixtures/game_data.php`)

Sparse on purpose: only IDs needed by existing tests. When a new test needs another building/ship/cost:

1. Add the minimal `$GLOBALS['resource']` / `$pricelist` / `$reslist` entries there (or in the test’s `setUp` via `array_replace`)
2. Prefer `RESOURCE_*` / `SHIP_*` constants for keys when the constant exists

## Database fakes (`tests/Support/`)

| Helper | Use |
|--------|-----|
| `FakeDatabase` | In-memory stand-in for `Database::get()` |
| `SwapDatabaseInstance` | Trait: swap the singleton for the fake in `setUp` / restore in `tearDown` |
| `FakePlanetQueryHandler` / `FakeFleetQueryHandler` / … | Narrower stubs for specific query shapes |
| `*DatabaseStub.php` | Feature-specific fakes (achievements, Discord, Hive memo, …) |

Typical pattern:

```php
require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class ExampleTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new FakeDatabase();
        $this->swapDatabase($this->fake);
    }
}
```

Inspect an existing neighbor test under `tests/Unit/` before inventing a new stub.

## What not to do

- Do not boot `includes/common.php` or hit a real MySQL DB in unit tests (integration suite is separate).
- Do not add production logic only in `Show*Page` if you need coverage-gated tests — extract a service first.
- Do not assume `$USER` / `$PLANET` globals exist; pass arrays into the code under test.
