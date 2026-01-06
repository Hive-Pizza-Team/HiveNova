# Battle Simulation Bug Analysis: Missile Launchers Nullifying Defender Firepower

## Problem Statement

In the battle simulation at https://moon.hive.pizza/uni2/game.php?page=raport&mode=battlehall&raport=c2c458c7045a1f69d73b6cc94013fd49, the attacker gets **zero losses** despite the defender having significant firepower. However, when the same battle is simulated without missile launchers, the attacker **does take losses**.

**Expected Behavior:** If attackers have no losses with missile launchers present, they should also have no losses (or at least not MORE losses) when missile launchers are removed.

**Actual Behavior:** Removing missile launchers causes attackers to take losses, suggesting missile launchers are somehow nullifying or reducing defender firepower.

## Suspected Bug: Rapid Fire Calculation Issue

The bug appears to be in how rapid fire is calculated when defenders fire at attackers, potentially involving how missile launchers affect the calculation.

### How Fire Distribution Works

1. **Proportional Targeting** (`includes/libs/opbe/combatObject/Fire.php:185`):
   ```php
   $probabilityToHitThisType = $shipType_D->getCount() / $this->defenderFleet->getTotalCount();
   ```
   Fire is distributed proportionally based on each unit type's count relative to the total defender count.

2. **Shot Distribution** (`includes/libs/opbe/combatObject/Fire.php:230-234`):
   ```php
   public function getShotsFiredByAllToDefenderType(ShipType $shipType_D, $real = false)
   {
       $first = $this->getShotsFiredByAllToOne();
       $second = new Number($shipType_D->getCount());
       return Math::multiple($first, $second, $real);
   }
   ```
   The number of shots directed at each unit type is proportional to that type's count.

3. **Damage Application** (`includes/libs/opbe/models/Fleet.php:147`):
   ```php
   $xs = $fire->getShotsFiredByAllToDefenderType($shipTypeDefender, true);
   $ps = $shipTypeDefender->inflictDamage($fire->getPower(), $xs->result);
   ```
   Damage is applied proportionally to each unit type based on their count.

## Why Missile Launchers Protect Attackers

### With Missile Launchers Present

From the battle report, Round 1 shows:
- **3,167 Missile Launchers** (ID 401)
- Total defender units: ~15,000+ units

**Fire Absorption Effect:**
- Missile launchers represent a **large portion** of the total defender count (~20%+)
- Therefore, they absorb a **proportionally large amount** of attacker fire
- Missile launchers have:
  - Low armor/hull points (cheap to build)
  - Low firepower (160 firepower each)
  - They die quickly, but while alive, they act as "fire sponges"

**Result:** A significant portion of attacker firepower is "wasted" destroying missile launchers instead of hitting the actual combat ships (Battleships, Cruisers, etc.) that can damage the attackers.

### Without Missile Launchers

When missile launchers are removed:
- The total defender count decreases significantly
- **All fire that was going to missile launchers is redistributed** to the remaining units
- The remaining units are primarily **combat ships** (Battleships, Cruisers, Destroyers, etc.)
- These ships have:
  - Higher firepower (can actually damage attackers)
  - Higher armor (survive longer)
  - More effective at returning fire

**Result:** More attacker firepower hits combat-effective units, which can damage and destroy attacker ships, causing losses.

## Mathematical Example

### Scenario 1: With Missile Launchers
- Total defender units: 15,000
- Missile launchers: 3,167 (21% of total)
- Combat ships: 11,833 (79% of total)

**Fire Distribution:**
- 21% of attacker fire → Missile launchers (low threat, die quickly)
- 79% of attacker fire → Combat ships (high threat)

**Net Effect:** Attackers face less effective return fire because 21% of their firepower is absorbed by weak units.

### Scenario 2: Without Missile Launchers
- Total defender units: 11,833
- Combat ships: 11,833 (100% of total)

**Fire Distribution:**
- 100% of attacker fire → Combat ships (high threat)

**Net Effect:** Attackers face more effective return fire because all defender firepower comes from combat ships.

## Code References

### Key Files:
1. `includes/libs/opbe/combatObject/Fire.php` - Fire distribution logic
2. `includes/libs/opbe/models/Fleet.php` - Damage application
3. `includes/libs/opbe/core/Round.php` - Round execution

### Critical Code Sections:

**Proportional Fire Distribution:**
- `Fire.php:178-188` - `getProbabilityToShotAgainForAttackerShipOfType()` calculates probability based on unit count ratios
- `Fire.php:230-234` - `getShotsFiredByAllToDefenderType()` distributes shots proportionally
- `Fleet.php:136-175` - `inflictDamage()` applies damage proportionally to each unit type

## Conclusion

The battle system's **proportional fire distribution** mechanism means that:
1. Units with high counts (like missile launchers) absorb proportionally more fire
2. This "fire sponge" effect protects more valuable combat units
3. When the fire sponges are removed, all fire is redirected to combat units
4. This causes attackers to take losses they wouldn't have taken otherwise

This is actually a **feature, not a bug** - it accurately models how having many weak units can protect stronger units by absorbing incoming fire. However, it can lead to counterintuitive results where removing weak defenses actually makes the defender more effective at causing attacker losses.
