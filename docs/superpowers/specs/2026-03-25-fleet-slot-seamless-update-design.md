# Fleet Slot Seamless Update — Design Spec

**Date:** 2026-03-25
**Branch:** feat-attack-notification
**Issue:** UX improvement — fleet page does not update when a slot opens up

---

## Problem

When a player navigates to the fleet page with all fleet slots occupied (e.g. after following an attack link from a spy report), the "Continue" button is hidden. If they wait for a fleet to return home, the page does not update — the slot counter stays stale and the button stays hidden. They must navigate away and back to proceed.

---

## Approach

Client-side only. Fleet return times are already embedded in the DOM as `data-fleet-end-time` attributes on each `.fleets` table cell. The existing countdown JS in `fleetTable.js` already ticks every second. We extend it to detect when a fleet's countdown reaches zero and seamlessly update the slot counter and reveal the "Continue" button.

No new PHP endpoints, no server polling, no page reload.

---

## Template Changes (`styles/templates/game/page.fleetTable.default.tpl`)

### 1. Slot counter span

Wrap the active fleet slot count in a labelled span so JS can update it:

```smarty
{* Before *}
<div ...>{$LNG.fl_fleets} {$activeFleetSlots} / {$maxFleetSlots}</div>

{* After *}
<div ...>{$LNG.fl_fleets} <span id="activeFleetSlots" data-max="{$maxFleetSlots}">{$activeFleetSlots}</span> / {$maxFleetSlots}</div>
```

### 2. Continue button row

Always render the row, but hide it server-side when slots are full. JS removes the style when a slot opens:

```smarty
{* Before *}
{if $maxFleetSlots != $activeFleetSlots}
<tr style="height:20px;"><td colspan="4"><input type="submit" value="{$LNG.fl_continue}"></td>
{/if}

{* After *}
<tr id="fleetContinueRow" style="height:20px;{if $maxFleetSlots == $activeFleetSlots}display:none;{/if}">
  <td colspan="4"><input type="submit" value="{$LNG.fl_continue}"></td>
</tr>
```

---

## JavaScript Changes (`scripts/game/fleetTable.js`)

Extend the existing `setInterval` to:

1. Track which fleet rows have already been "expired" (to fire the decrement only once per fleet).
2. When a countdown hits `<= 0` for the first time, decrement `activeSlots`.
3. Update `#activeFleetSlots` text.
4. If `activeSlots < maxSlots`, show `#fleetContinueRow`.

```js
$(function() {
    var slotSpan = $('#activeFleetSlots');
    var activeSlots = parseInt(slotSpan.text(), 10);
    var maxSlots = parseInt(slotSpan.data('max'), 10);

    window.setInterval(function() {
        $('.fleets').each(function() {
            var $el = $(this);
            var s = $el.data('fleet-time') - (serverTime.getTime() - startTime) / 1000;
            if (s <= 0) {
                if (!$el.data('slot-freed')) {
                    $el.data('slot-freed', true);
                    activeSlots = Math.max(0, activeSlots - 1);
                    slotSpan.text(activeSlots);
                    if (activeSlots < maxSlots) {
                        $('#fleetContinueRow').show();
                    }
                }
                $el.text('-');
            } else {
                $el.text(GetRestTimeFormat(s));
            }
        });
    }, 1000);
});
```

---

## Scope

- No new PHP files or endpoints.
- No changes to `ShowFleetTablePage.php`.
- Two files changed: one template, one JS.
- No CSS changes needed.

---

## Testing

1. Log in with an account that has limited fleet slots (e.g. 2).
2. Send spy missions until all slots are used.
3. Navigate to the fleet table page — verify "Continue" button is hidden.
4. Wait for the earliest spy probe to return — verify:
   - Slot counter decrements from `2 / 2` to `1 / 2` without page reload.
   - "Continue" button appears.
5. Verify the button works — proceeds to fleet step 1 correctly.
6. Run `./tests/run-ci-local.sh` — no regressions.
