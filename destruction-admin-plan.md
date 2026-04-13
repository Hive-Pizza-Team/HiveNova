# Admin: Solar System & Galaxy Destruction Tool

A narrative/lore admin tool for destroying solar systems or entire galaxies as a storyline event — relocating affected players, redirecting fleets, cleaning up debris, and notifying users.

---

## Background & Problem

HiveNova's storyline for one universe involves solar systems and entire galaxies being destroyed as plot events. Currently this requires manual SQL — deleting rows from `%%PLANETS%%`, redirecting fleets in `%%FLEETS%%`, handling moons linked via `id_luna`, and messaging affected players by hand. This is error-prone and tedious at scale.

The existing `MissionCaseDestruction` mission handles moon destruction for a single target triggered by a fleet, but there is no admin-initiated bulk destruction for a whole system or galaxy.

---

## Data Model Reference

**`%%PLANETS%%` key columns:**
- `galaxy`, `system`, `planet` — coordinate triplet
- `planet_type` — `1` = planet, `3` = moon
- `universe` — universe ID
- `id_owner` — owning user ID (NULL = uninhabited)
- `id_luna` — ID of linked moon row (0 if none)

**`%%FLEETS%%` key columns:**
- `fleet_start_galaxy/system/planet`, `fleet_start_id`, `fleet_start_type`
- `fleet_end_galaxy/system/planet`, `fleet_end_id`, `fleet_end_type`
- `fleet_universe`

**`%%USERS%%` key columns:**
- `id_planet` — current main planet ID
- `galaxy`, `system`, `planet` — cached coordinates of main planet

---

## Scope

Two destruction modes, both admin-only (`AUTH_ADM`):

| Mode | Target | What's destroyed |
|------|--------|-----------------|
| **System** | Galaxy + System (e.g. 3:147) | All planets and moons in that system |
| **Galaxy** | Galaxy number (e.g. 3) | All planets and moons in the entire galaxy |

Both modes share the same logic pipeline, just with a broader WHERE clause for galaxy mode.

---

## New File

**`includes/pages/adm/ShowDestructionPage.php`**

Follows the same pattern as other admin pages (no `Show` prefix in routing, function named `ShowDestructionPage()`). Registered in the admin nav via `ShowMenuPage.php` / `ShowTopnavPage.php`.

---

## Logic Pipeline (both modes)

### Step 1 — Preview / Confirmation screen

Before executing, show the admin:
- Count of inhabited planets and moons affected
- Count of players who will be displaced
- Count of in-flight fleets that start or end in the target zone
- A confirmation button (with a CSRF/session token check, same pattern as other admin pages)

### Step 2 — Resolve in-flight fleets

Classify every fleet that touches the destroyed zone:

| Origin | Destination | Outcome |
|--------|-------------|---------|
| Anywhere | In zone | **Lost** — cancel fleet, no refund (there was warning). Ships and cargo are gone. |
| In zone | Safe | **Survives** — let it complete. Update `fleet_start_*` cosmetically to a null/destroyed marker so return-trip routing isn't broken, but do not cancel. |
| In zone | In zone | **Lost** — same as destination-in-zone case above. |

For **lost** fleets: delete the fleet row (do not return resources or ships).
For **surviving** fleets: update `fleet_start_id` / `fleet_start_galaxy/system/planet/type` to the player's new home planet (assigned in Step 3), so any return leg has a valid landing point.

### Step 3 — Identify and (optionally) relocate affected players

If the admin enabled relocation:
- For each player with `id_planet` in the target zone (main planet being destroyed):
  - Find or create an uninhabited slot per the chosen strategy (exact coordinates or random slot in target galaxy).
  - Update `users.id_planet`, `users.galaxy`, `users.system`, `users.planet` to the new location.
- Players whose only affected planets are colonies (non-main) simply lose those colonies — no relocation needed.

If relocation is disabled:
- Players whose main planet is in the zone have `id_planet` set to 0 (or the first surviving planet they own, if any).
- It is the admin's responsibility to ensure this is intentional (the preview screen will warn if any player will be left with no planets).

### Step 4 — Delete planets and moons

```sql
-- System destruction example
DELETE FROM %%PLANETS%%
WHERE universe = :uni AND galaxy = :galaxy AND system = :system;
```

Moon rows are also in `%%PLANETS%%` (planet_type = 3) and are included in the same DELETE. Also zero out `id_luna` on any remaining planets that referenced a now-deleted moon (though in a full system/galaxy destruction this cleans itself up).

### Step 5 — Notify affected players

Send an in-game message (via the `%%MESSAGES%%` table, same pattern as mission notifications) to each displaced player explaining:
- Their planets in the zone were destroyed by a story event.
- Their main base has been relocated to [new coordinates].
- Any fleets en route to the zone have been redirected.

Message text should be configurable — either a text area in the admin form or a language string (start with a text area so admins can customize lore text per event).

---

## Admin UI (template: `DestructionPage.tpl`)

**Form fields:**
- Universe selector (dropdown of available universes — reuse the pattern from `ShowUniversePage.php`)
- Mode: `System` or `Galaxy` (radio button)
- Galaxy number (1–max_galaxy from config)
- System number (1–max_system from config, shown/hidden based on mode)
- **Relocation**: checkbox "Relocate displaced players"
  - If checked: sub-option radio: `Exact coordinates` (galaxy:system:slot inputs) or `Random in galaxy` (galaxy input only)
- **Debris fields**: checkbox "Leave debris fields" (default checked)
- **Broadcast**: checkbox "Send universe-wide announcement" (default checked)
- Custom notification/lore message (textarea, sent to affected players and as broadcast text)
- Preview button → shows affected count, then Confirm Destroy button

**Confirmation state:**
- POST with `action=preview`: return JSON or re-render with counts (no DB changes)
- POST with `action=destroy` + session token: execute pipeline

---

## Edge Cases to Handle

| Scenario | Handling |
|----------|----------|
| Player's only planet is in the zone | If relocation on: assign new planet before deleting. If off: warn in preview, set `id_planet=0` |
| Fleet returning home to a destroyed planet | Origin was in zone, destination safe → survives; update `fleet_start_*` to new home |
| Fleet heading to destroyed zone | Lost — no refund |
| ACS fleet with mixed origin/destination | Treat each fleet row individually by origin+destination check |
| Uninhabited planets in zone | Just delete — no notification needed |
| Moon linked to planet in zone | Deleted by the same DELETE; no orphan cleanup needed for full-zone destruction |
| Admin destroys zone with zero planets | Show "no planets found" and abort |
| Player has no valid relocation target | Fall back to slot-finding loop; if truly no space, warn admin and skip relocation for that player |
| Debris fields enabled | Write `der_metal`/`der_crystal` to player's new home planet (if relocation on) before deleting; otherwise debris is dropped — debris lives on the `%%PLANETS%%` row itself, not a separate table |
| Universe-wide broadcast checked | Insert a message to all users in the universe in addition to individual displacement messages |

---

## Files to Create/Modify

| File | Change |
|------|--------|
| `includes/pages/adm/ShowDestructionPage.php` | New page controller |
| `styles/templates/adm/DestructionPage.tpl` | New Smarty template |
| `styles/templates/adm/ShowMenuPage.tpl` | Add nav link |
| `language/en/ADMIN.php` | Add EN strings |
| All other `language/*/ADMIN.php` | Add matching keys (at minimum copy EN as placeholder) |

---

## Decisions (resolved)

1. **Relocation** — Optional per destruction event. If disabled, players simply lose their planets (no relocation). If enabled, admin chooses:
   - **Exact coordinates** — admin specifies a galaxy:system:slot to place displaced players.
   - **Random in galaxy** — admin specifies a target galaxy; the tool finds an uninhabited slot at random within it.

2. **Fleet handling** — Destination determines fate:
   - Fleet heading **to** destroyed zone → **lost** (no refund; there was warning).
   - Fleet heading **from** destroyed zone to a **safe** destination → **survives**; let it complete normally. Update `fleet_start_*` cosmetically to reflect origin is gone, but do not cancel.
   - Fleet heading **from** destroyed zone **to** destroyed zone → **lost** (both endpoints gone).

3. **Debris fields** — Optional per event, **default ON**. When enabled, create debris field entries for destroyed inhabited planets (same resource split as combat debris).

4. **Notifications** — Admin panel exposes:
   - Per-event custom lore text for the individual displacement message (already planned).
   - Checkbox: **universe-wide broadcast** to all players (separate from individual messages). Warning announcements are handled separately by existing tooling; this is the destruction-event announcement.

5. **Logging** — Yes, always log to `%%LOG%%`: who triggered it, mode (system/galaxy), coordinates, counts (planets destroyed, players relocated, fleets lost, fleets survived).

---

## Implementation Notes (from codebase exploration)

### Admin page anatomy
- Permission check at top — this page uses the strict `AUTH_ADM + session_id()` guard (same as `ShowUniversePage.php`, not the softer `allowedTo()`) because it is irreversibly destructive.
- One top-level function `ShowDestructionPage()` — no class, no namespace (admin pages don't use namespaces).
- Input via `HTTP::_GP('field', default)`.
- Output via `new Template()` → `assign_vars([...])` → `show('DestructionPage.tpl')`.

### Database
- Admin pages historically use `$GLOBALS['DATABASE']` (legacy API). New code we write will use `Database::get()` with `%%TABLE%%` substitution and parameterized queries — the clean PDO pattern used across `includes/classes/`.

### Debris storage
- Debris is NOT in a separate table. It lives in `der_metal` / `der_crystal` columns on the `%%PLANETS%%` row.
- For our tool: if debris is enabled, add the destroyed planet's resources (at the configured CDR split) to the player's new home planet's debris columns before deleting. If relocation is off or the player has no surviving planet, debris is dropped.

### Logging
- Use `Log` class (`HiveNova\Core\Log`). Existing modes: 1=player, 2=planet, 3=settings, 4=present.
- **Use mode 5 for destruction events.**
- Call `saveTr()` (the PDO version), not the legacy `save()`.
- Store a serialized summary array in the `data` field: mode, target coords, counts (planets deleted, players relocated, fleets lost, fleets survived).

### Messages
- `PlayerUtil::sendMessage($userId, 0, $senderName, $type, $subject, $text, TIMESTAMP, null, 1, $universe)`
- Message type 3 = combat/tower, 4 = fleet return, 50 = admin broadcast (used in `ShowSendMessagesPage.php`). Use **type 50** for admin destruction notifications so they appear in the same inbox slot as other admin messages.

### Nav
- Link lives in `styles/templates/adm/ShowMenuPage.tpl` (the template, not the PHP file — `ShowMenuPage.php` only passes data, the template has the actual `<li>` entries).
- Pattern: `{if allowedTo('ShowDestructionPage')}<li><a href="?page=destruction" target="Hauptframe">{$LNG.mu_destruction}</a></li>{/if}`
- Add under the `mu_tools` section (or a new "Universe Events" section heading if preferred).
- URL slug is `destruction` → maps to `ShowDestructionPage.php` by the admin router.

### Build order
1. Nav entry — `ShowMenuPage.tpl` + language key `mu_destruction`
2. Page skeleton — `ShowDestructionPage.php` with permission check + template render (no DB logic)
3. Template — `DestructionPage.tpl` with full form UI
4. Language strings — all 8 language files
5. Preview logic — count affected planets / players / fleets (read-only queries)
6. Execute: fleets — classify and process in-flight fleets
7. Execute: relocate players — optional relocation logic
8. Execute: delete planets + debris — DELETE with optional debris carry-forward
9. Execute: notifications — individual messages + optional broadcast
10. Execute: logging — write mode-5 entry to `%%LOG%%`
