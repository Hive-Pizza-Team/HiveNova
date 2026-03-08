<?php

use PHPUnit\Framework\TestCase;

/**
 * OPBE battle engine tests.
 *
 * Ship stats used throughout (signature: shipId, count, shield, power, cost):
 *   id=204  shield=10  power=50   cost=[3000, 1000]  (Light Fighter equivalent)
 *   id=206  shield=25  power=150  cost=[6000, 4000]  (Heavy Fighter equivalent)
 *
 * IDs are within ID_MIN_SHIPS(100)..ID_MAX_SHIPS(300) so they're treated as
 * ships (not defenses) by the debris calculations.
 */
class OPBEBattleTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** Build a Ship with explicit combat stats — no DB required. */
    private function makeShip(int $id, int $count, float $shield, float $power, array $cost, int $weaponsTech = 0, int $shieldsTech = 0, int $armourTech = 0): Ship
    {
        return new Ship($id, $count, [], $shield, $cost, $power, $weaponsTech, $shieldsTech, $armourTech);
    }

    /** Wrap a single Ship in a PlayerGroup ready for Battle. */
    private function singleShipGroup(int $playerId, int $shipId, int $count, float $shield, float $power, array $cost = [3000, 1000]): PlayerGroup
    {
        $fleet  = new Fleet(1);
        $fleet->addShipType($this->makeShip($shipId, $count, $shield, $power, $cost));
        $player = new Player($playerId, [$fleet]);
        $group  = new PlayerGroup();
        $group->addPlayer($player);
        return $group;
    }

    /** Run a battle and return the BattleReport. */
    private function runBattle(PlayerGroup $attackers, PlayerGroup $defenders): BattleReport
    {
        $battle = new Battle($attackers, $defenders);
        $battle->startBattle(false); // false = suppress echo output
        return $battle->getReport();
    }

    // -----------------------------------------------------------------------
    // Outcome tests
    // -----------------------------------------------------------------------

    public function test_attacker_wins_with_overwhelming_force(): void
    {
        // 500 heavy fighters vs 1 light fighter — attacker should always win
        $attackers = $this->singleShipGroup(1, 206, 500, 25, 150, [6000, 4000]);
        $defenders = $this->singleShipGroup(2, 204,   1, 10,  50, [3000, 1000]);

        $report = $this->runBattle($attackers, $defenders);

        $this->assertTrue($report->attackerHasWin(), 'Attacker with 500 ships vs 1 should win');
        $this->assertFalse($report->defenderHasWin());
        $this->assertFalse($report->isAdraw());
    }

    public function test_defender_wins_with_overwhelming_force(): void
    {
        // 1 light fighter vs 500 heavy fighters — defender should always win
        $attackers = $this->singleShipGroup(1, 204,   1, 10,  50, [3000, 1000]);
        $defenders = $this->singleShipGroup(2, 206, 500, 25, 150, [6000, 4000]);

        $report = $this->runBattle($attackers, $defenders);

        $this->assertTrue($report->defenderHasWin(), 'Defender with 500 ships vs 1 attacker should win');
        $this->assertFalse($report->attackerHasWin());
        $this->assertFalse($report->isAdraw());
    }

    public function test_draw_when_neither_side_can_penetrate_shields(): void
    {
        // attack=0 → damage is zero → all shots bounce → no hull damage → draw after all rounds
        $attackers = $this->singleShipGroup(1, 204, 10, 1000000, 0, [1000, 1000]);
        $defenders = $this->singleShipGroup(2, 204, 10, 1000000, 0, [1000, 1000]);

        $report = $this->runBattle($attackers, $defenders);

        $this->assertTrue($report->isAdraw(), 'With zero attack power neither side loses ships — should draw');
        $this->assertFalse($report->attackerHasWin());
        $this->assertFalse($report->defenderHasWin());
    }

    // -----------------------------------------------------------------------
    // Losses / damage tests
    // -----------------------------------------------------------------------

    public function test_defender_takes_losses_when_attacker_wins(): void
    {
        $attackers = $this->singleShipGroup(1, 206, 200, 25, 150, [6000, 4000]);
        $defenders = $this->singleShipGroup(2, 204,  10, 10,  50, [3000, 1000]);

        $report = $this->runBattle($attackers, $defenders);

        $this->assertGreaterThan(0, $report->getTotalDefendersLostUnits(), 'Defender should have lost resources');
    }

    public function test_both_sides_take_losses_in_balanced_fight(): void
    {
        // Equal fleets — attacker should take some damage (this was broken when
        // shots were counted as 0 due to the cargo-bug class of issue)
        $attackers = $this->singleShipGroup(1, 204, 50, 10, 50, [3000, 1000]);
        $defenders = $this->singleShipGroup(2, 204, 50, 10, 50, [3000, 1000]);

        $report = $this->runBattle($attackers, $defenders);

        $this->assertGreaterThan(0, $report->getTotalAttackersLostUnits(), 'Attacker should take damage in a balanced fight');
        $this->assertGreaterThan(0, $report->getTotalDefendersLostUnits(), 'Defender should take damage in a balanced fight');
    }

    public function test_no_hull_damage_when_attack_below_shield_threshold(): void
    {
        // Single shot of damage=1 vs shield=10 per cell: clamp() returns 0 → all bounced
        $attackers = $this->singleShipGroup(1, 204, 1, 10, 1, [1000, 1000]);
        $defenders = $this->singleShipGroup(2, 204, 1, 10, 1, [1000, 1000]);

        $report = $this->runBattle($attackers, $defenders);

        // Both sides fire 1 shot of damage=1 against 10-shield ships → all bounced → draw, no losses
        $this->assertEquals(0, $report->getTotalDefendersLostUnits(), 'Sub-shield damage should inflict no hull damage');
        $this->assertEquals(0, $report->getTotalAttackersLostUnits(), 'Sub-shield damage should inflict no hull damage');
    }

    // -----------------------------------------------------------------------
    // Debris tests
    // -----------------------------------------------------------------------

    public function test_attacker_debris_is_zero_when_attacker_loses_nothing(): void
    {
        // Overwhelming attacker — loses nothing
        $attackers = $this->singleShipGroup(1, 206, 1000, 25, 150, [6000, 4000]);
        $defenders = $this->singleShipGroup(2, 204,    1, 10,  50, [3000, 1000]);

        $report = $this->runBattle($attackers, $defenders);

        [$metal, $crystal] = $report->getAttackerDebris(SHIP_DEBRIS_FACTOR, DEFENSE_DEBRIS_FACTOR);
        $this->assertEquals(0, $metal,   'No attacker losses → zero metal debris');
        $this->assertEquals(0, $crystal, 'No attacker losses → zero crystal debris');
    }

    public function test_defender_debris_proportional_to_losses(): void
    {
        // 1 defender ship costs [3000 metal, 1000 crystal]
        // Max debris = cost * SHIP_DEBRIS_FACTOR (0.3) = [900, 300]
        $attackers = $this->singleShipGroup(1, 206, 100, 25, 150, [6000, 4000]);
        $defenders = $this->singleShipGroup(2, 204,   1, 10,  50, [3000, 1000]);

        $report = $this->runBattle($attackers, $defenders);

        [$metal, $crystal] = $report->getDefenderDebris(SHIP_DEBRIS_FACTOR, DEFENSE_DEBRIS_FACTOR);
        $this->assertGreaterThan(0, $metal,   'Destroyed defender ship should generate metal debris');
        $this->assertGreaterThan(0, $crystal, 'Destroyed defender ship should generate crystal debris');
        $this->assertLessThanOrEqual(900,  $metal,   'Debris cannot exceed cost * debris factor');
        $this->assertLessThanOrEqual(300,  $crystal, 'Debris cannot exceed cost * debris factor');
    }

    // -----------------------------------------------------------------------
    // Tech bonus tests (via ShipType directly)
    // -----------------------------------------------------------------------

    public function test_weapons_tech_increases_attack_power(): void
    {
        $base    = $this->makeShip(204, 1, 10, 100, [3000, 1000], 0, 0, 0);
        $boosted = $this->makeShip(204, 1, 10, 100, [3000, 1000], 5, 0, 0);

        $this->assertGreaterThan(
            $base->getCurrentPower(),
            $boosted->getCurrentPower(),
            'Weapons tech level 5 should yield more attack than level 0'
        );
    }

    public function test_armour_tech_increases_hull(): void
    {
        $base    = $this->makeShip(204, 1, 10, 50, [3000, 1000], 0, 0, 0);
        $boosted = $this->makeShip(204, 1, 10, 50, [3000, 1000], 0, 0, 5);

        $this->assertGreaterThan(
            $base->getCurrentLife(),
            $boosted->getCurrentLife(),
            'Armour tech level 5 should yield more HP than level 0'
        );
    }

    public function test_shields_tech_increases_shield(): void
    {
        $base    = $this->makeShip(204, 1, 10, 50, [3000, 1000], 0, 0, 0);
        $boosted = $this->makeShip(204, 1, 10, 50, [3000, 1000], 0, 5, 0);

        $this->assertGreaterThan(
            $base->getCurrentShield(),
            $boosted->getCurrentShield(),
            'Shield tech level 5 should yield more shield than level 0'
        );
    }

    // -----------------------------------------------------------------------
    // Round tracking
    // -----------------------------------------------------------------------

    public function test_battle_runs_at_most_max_rounds(): void
    {
        $attackers = $this->singleShipGroup(1, 204, 10, 10, 50, [3000, 1000]);
        $defenders = $this->singleShipGroup(2, 204, 10, 10, 50, [3000, 1000]);

        $report = $this->runBattle($attackers, $defenders);

        $this->assertLessThanOrEqual(ROUNDS, $report->getLastRoundNumber(), 'Battle should not exceed ROUNDS constant');
    }

    public function test_battle_ends_early_when_one_side_wiped_out(): void
    {
        // Massively one-sided — should end before round 6
        $attackers = $this->singleShipGroup(1, 206, 5000, 25, 999999, [6000, 4000]);
        $defenders = $this->singleShipGroup(2, 204,    1, 10,     50, [3000, 1000]);

        $report = $this->runBattle($attackers, $defenders);

        $this->assertLessThan(ROUNDS, $report->getLastRoundNumber(), 'One-sided battle should end before max rounds');
    }
}
