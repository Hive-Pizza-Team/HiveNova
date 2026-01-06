# Debug Logging Added for Missile Launcher Bug Investigation

## Summary
Comprehensive debug logging has been added to track firepower calculations, rapid fire, and shot distribution to identify why missile launchers (ID 401) are causing attackers to have zero losses.

## Files Modified

### 1. `includes/libs/opbe/combatObject/Fire.php`
Added logging to track:
- Fire object creation for missile launchers (ID 401)
- Base firepower calculations (before rapid fire)
- Final firepower calculations (after rapid fire)
- Rapid fire calculations per target unit type
- Rapid fire probability calculations
- Shot distribution calculations

**Key Debug Points:**
- `__construct()`: Logs missile launcher fire creation with power and shots
- `calculateTotal()`: Logs base and final firepower for units
- `calculateRf()`: Logs rapid fire shots and power calculations
- `getProbabilityToShotAgainForAttackerShipOfType()`: Logs rapid fire probability breakdown
- `getShotsFiredByAllToOne()`: Logs shot distribution calculations

### 2. `includes/libs/opbe/core/Round.php`
Added logging to track:
- Total defender firepower breakdown per unit type
- Total defender firepower before applying to attackers
- Firepower per unit for each defender unit type
- Special highlighting for missile launchers (ID 401)

**Key Debug Points:**
- `startRound()`: Logs complete defender firepower breakdown showing:
  - Unit ID and count
  - Total power per unit type
  - Power per individual unit
  - Total shots per unit type
  - Total defender firepower

### 3. `includes/libs/opbe/models/Fleet.php`
Added logging to track:
- Total incoming firepower and shots
- Shot distribution per target unit
- Fire details when missile launchers are firing

**Key Debug Points:**
- `inflictDamage()`: Logs total incoming fire and shot distribution details

## What to Look For

When running a battle simulation, check the debug logs for:

1. **Missile Launcher Firepower**: 
   - Does missile launcher (ID 401) firepower get calculated correctly?
   - Is it being added to total defender firepower?

2. **Rapid Fire Issues**:
   - Do missile launchers have zero rapid fire against all attacker types?
   - Is rapid fire calculation reducing their effective firepower?

3. **Shot Distribution**:
   - Are shots from missile launchers being distributed correctly?
   - Is the target fleet total count correct when defenders fire?

4. **Total Firepower Comparison**:
   - Compare total defender firepower WITH missile launchers vs WITHOUT
   - Check if missile launchers are somehow nullifying other units' firepower

## How to Use

1. Run a battle simulation with missile launchers present
2. Check the debug output (usually in battle report or logs)
3. Look for lines starting with "DEBUG" or "*** DEBUG"
4. Compare the firepower values with and without missile launchers
5. Pay special attention to lines mentioning "MISSILE LAUNCHER" or "ID: 401"

## Expected Output Format

```
*** DEBUG DEFENDER FIREPOWER BREAKDOWN - Round 1 ***
Total Defender Firepower: 1890696
Unit ID: 401 [MISSILE LAUNCHER], Count: 3167, Total Power: 506720, Power/Unit: 160, Shots: 3167
Unit ID: 202, Count: 2931, Total Power: 293100, Power/Unit: 100, Shots: 2931
...
*** END DEFENDER FIREPOWER BREAKDOWN ***

DEBUG Fire::calculateTotal - Unit ID: 401, Count: 3167, Base Power: 506720, Base Shots: 3167
DEBUG Fire::calculateRf - Unit ID: 401, RF Shots Per Unit: 0, Total RF Shots: 0, RF Power: 0
DEBUG Fire::calculateTotal - Unit ID: 401, Final Power: 506720, Final Shots: 3167
```

## Next Steps

After reviewing the debug output:
1. Identify where missile launcher firepower is being lost or nullified
2. Check if rapid fire calculations are incorrect
3. Verify shot distribution is working correctly
4. Compare with battle simulation without missile launchers to see the difference
