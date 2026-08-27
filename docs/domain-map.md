# Domain map — where does X live?

Quick index for agents. Prefer the service/class column for logic; pages are HTTP + templates only.

| Area | Start here | Notes |
|------|------------|--------|
| Fleet send (step 3 validation / insert) | `FleetDispatchService` | Pages: `ShowFleetStep1/2/3Page` |
| Fleet mission availability | `FleetMissionAvailability` | Used when building mission selectors |
| Deduct ships / fuel on send | `FleetPlanetDeduction` | Locked planet row |
| Flight math, slots, bash helpers | `FleetFunctions` | Still large; extract when touching |
| Flying fleets cron / arrive | `FlyingFleetHandler` + `includes/classes/missions/MissionCase*` | Combat shared via `MissionCaseCombat` |
| Fleet event list / tooltips | `FlyingFleetsTable` | |
| Incoming attack banner | `IncomingHostileFleetQuery` | |
| Economy tick / build queues | `ResourceUpdate`, `ResourceCalculator` | Heavy global mutation |
| Buildings / research / shipyard UI | `ShowBuildingsPage`, `ShowResearchPage`, `ShowShipyardPage` | Costs via `BuildFunctions` |
| Galaxy rows / fog | `GalaxyRows`, `ShowGalaxyPage` | Prefer injected user/planet |
| Alliance (huge page) | `ShowAlliancePage` + `AllianceService`, `AllianceDiplomacyService`, `AllianceRankService` | Keep extracting |
| Messages / buddy | `ShowMessagesPage`, `MessageRepository`, `BuddyRepository` | |
| Overview | `ShowOverviewPage`, `scripts/game/overview*.js` | Planet viz is JS-heavy |
| Settings / Hive link / PIZZA deposit | `ShowSettingsPage`, `scripts/game/base.js` (`DepositPizzaTokens`) | Info modal also uses deposit for resource 921 |
| Pizzabits (DM) resource id | `RESOURCE_DARKMATTER` (921) | Column still `darkmatter` |
| Achievements / feats / directives | `AchievementService`, `FeatService`, `DirectiveService` (+ Hooks/Catalog) | |
| Seasons / wipe / Pizza prizes | `SeasonService`, `SeasonReportComposer`, `DatabaseSeasonStore` | |
| Hive chain I/O | `HiveTransfer`, `HiveEngineTransfer`, `HiveMemo`, `HiveUtil` | |
| Marketplace | `MarketPlaceResource`, fleet mission trade | |
| PvE salvage / NPC fleets | `PvePackageService`, `PveNpcFleetFactory`, `FLEET_MISSION_SALVAGE` | |
| Battle share to Hive | `BattleShareComposer` | |
| Admin CSRF / auth helpers | `AdminCsrf`, pages under `includes/pages/adm/` | |
| Cron tasks | `includes/classes/cronjob/*` | Register in `%%CRONJOBS%%` |
| DB table name tokens | `includes/dbtables.php` | `%%USERS%%`, `%%PLANETS%%`, … |
| Named mission / ship / resource IDs | `includes/constants.php` | See `docs/architecture.md` |

## Templates & client

| Surface | Path |
|---------|------|
| Shared game Smarty | `styles/templates/game/` |
| Default theme CSS | `styles/theme/hive/` |
| Game JS | `scripts/game/` |

## Related docs

- Conventions: `docs/architecture.md`
- Unit test fixtures: `docs/testing.md`
