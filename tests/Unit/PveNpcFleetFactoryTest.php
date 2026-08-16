<?php

use HiveNova\Core\PveNpcFleetFactory;
use PHPUnit\Framework\TestCase;

class PveNpcFleetFactoryTest extends TestCase
{
    public function testTemplatesAreFixedByFamilyAndTier(): void
    {
        $this->assertSame([204 => 8, 202 => 2], PveNpcFleetFactory::template('pirate', 1));
        $this->assertSame([205 => 10, 215 => 3], PveNpcFleetFactory::template('alien', 2));
        $this->assertSame([219 => 2, 207 => 2], PveNpcFleetFactory::template('salvager', 3));
    }

    public function testUnknownFamilyFallsBackToPirate(): void
    {
        $this->assertSame(PveNpcFleetFactory::template('pirate', 1), PveNpcFleetFactory::template('unknown', 1));
    }

    public function testAccusedBumpIncreasesCounts(): void
    {
        $base = PveNpcFleetFactory::template('pirate', 1, false);
        $bumped = PveNpcFleetFactory::template('pirate', 1, true);
        foreach ($base as $id => $count) {
            $this->assertSame((int) ceil($count * PVE_ACCUSED_SHIP_FACTOR), $bumped[$id]);
        }
    }

    public function testFamilyFromSeedBuckets(): void
    {
        $this->assertSame('pirate', PveNpcFleetFactory::familyFromSeed(10));
        $this->assertSame('alien', PveNpcFleetFactory::familyFromSeed(50));
        $this->assertSame('salvager', PveNpcFleetFactory::familyFromSeed(80));
    }

    public function testSyntheticPlayerUsesOwnerZero(): void
    {
        $player = PveNpcFleetFactory::syntheticPlayer('Aliens', 7);
        $this->assertSame(0, $player['id']);
        $this->assertSame('Aliens', $player['username']);
        $this->assertSame(7, $player['military_tech']);
    }
}
