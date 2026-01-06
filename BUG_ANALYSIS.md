# Bug Analysis: Missile Launchers Nullifying Defender Firepower

## Problem
- **With missile launchers:** Attacker has ZERO losses
- **Without missile launchers:** Attacker HAS losses
- **Expected:** Removing missile launchers should not increase attacker losses

## Code Flow Analysis

### When Defenders Fire at Attackers

1. **Round.php:102** - Creates Fire object for each defender unit:
   ```php
   $this->fire_d->add(new Fire($shipType, $attackersMerged));
   ```
   - `$shipType` = defender unit (e.g., missile launcher, battleship, etc.)
   - `$attackersMerged` = merged attacker fleet (correct)

2. **Fire.php:59-64** - Fire constructor:
   ```php
   public function __construct(ShipType $attackerShipType, Fleet $defenderFleet)
   {
       $this->attackerShipType = $attackerShipType;  // defender unit firing
       $this->defenderFleet = $defenderFleet;        // attacker fleet (target)
       $this->calculateTotal();
   }
   ```
   **NOTE:** Variable naming is confusing - `$defenderFleet` actually contains the TARGET fleet (attackers when defenders fire)

3. **Fire.php:101-109** - calculateTotal() calculates shots and power:
   ```php
   $this->shots += $this->attackerShipType->getCount();
   $this->power += $this->getNormalPower();
   if (USE_RF) {
       $this->calculateRf();
   }
   ```

4. **Fire.php:131-137** - calculateRf() adds rapid fire shots:
   ```php
   $tmpshots = round($this->getShotsFromOneAttackerShipOfType($this->attackerShipType) * $this->attackerShipType->getCount());
   $this->power += $tmpshots * $this->attackerShipType->getPower();
   $this->shots += $tmpshots;
   ```

5. **Fire.php:178-188** - getProbabilityToShotAgainForAttackerShipOfType():
   ```php
   foreach ($this->defenderFleet->getIterator() as $idFleet => $shipType_D)
   {
       $RF = $shipType_A->getRfTo($shipType_D);
       $probabilityToShotAgain = 1 - GeometricDistribution::getProbabilityFromMean($RF);
       $probabilityToHitThisType = $shipType_D->getCount() / $this->defenderFleet->getTotalCount();
       $p += $probabilityToShotAgain * $probabilityToHitThisType;
   }
   ```
   This iterates over the TARGET fleet (attackers) to calculate rapid fire probability.

## Potential Bug Locations

### Hypothesis 1: Missile Launchers Have Zero Rapid Fire
If missile launchers have zero rapid fire against all attacker ship types, and they represent a large portion of defender units, this could dilute the rapid fire calculation. However, rapid fire is calculated based on TARGET fleet (attackers), not firing fleet (defenders), so this shouldn't be the issue.

### Hypothesis 2: Shot Distribution Bug
When distributing shots from defenders to attackers, the code uses:
```php
$denum = new Number($this->defenderFleet->getTotalCount());
```
If `$this->defenderFleet` incorrectly includes defender units when defenders fire, this would dilute the shots per attacker unit.

### Hypothesis 3: Firepower Calculation Bug
The total firepower might be calculated incorrectly when missile launchers are present. Each defender unit creates its own Fire object, so missile launchers should contribute their firepower normally.

## Investigation Needed

1. Check if missile launchers have special rapid fire values (zero or very low)
2. Verify that when defenders fire, `$this->defenderFleet` only contains attacker units
3. Check if there's any code that filters out missile launchers from firepower calculations
4. Verify the total firepower calculation includes all defender units correctly

## Next Steps

1. Add debug logging to track:
   - Total defender firepower with/without missile launchers
   - Rapid fire calculations for each defender unit type
   - Shot distribution calculations
   
2. Check if there's a Defense class that handles missile launchers differently than Ship class

3. Verify rapid fire table - do any attacker ships have rapid fire against missile launchers that might affect calculations?
