<?php

declare(strict_types=1);

use HiveNova\Core\FeatCatalog;
use PHPUnit\Framework\TestCase;

class FeatCatalogTest extends TestCase
{
    public function testShipFeatKeysCoverAllFleetTypes(): void
    {
        $map = FeatCatalog::shipFeatKeys();
        $this->assertCount(19, $map);
        $this->assertSame(range(202, 220), array_keys($map));
        $this->assertSame(FeatCatalog::FIRST_DEATHSTAR, $map[214]);
        $this->assertSame(FeatCatalog::FIRST_BLACK_MOON, $map[216]);
    }

    public function testSpecialShipFeatsAreHidden(): void
    {
        $this->assertTrue(FeatCatalog::isHidden(FeatCatalog::FIRST_BLACK_MOON));
        $this->assertTrue(FeatCatalog::isHidden(FeatCatalog::FIRST_AVATAR));
        $this->assertTrue(FeatCatalog::isHidden(FeatCatalog::FIRST_PIZZABITS_COLLECTOR));
        $this->assertFalse(FeatCatalog::isHidden(FeatCatalog::FIRST_SMALL_CARGO));
        $this->assertFalse(FeatCatalog::isHidden(FeatCatalog::FIRST_DEATHSTAR));
        $this->assertFalse(FeatCatalog::isHidden(FeatCatalog::FIRST_SHIP));
    }

    public function testNewUniverseOpensEveryFeat(): void
    {
        foreach (FeatCatalog::keys() as $key) {
            $this->assertSame(
                FeatCatalog::STATUS_OPEN,
                FeatCatalog::initialStatus($key, true, true, true, true)
            );
        }
    }

    public function testLiveUniverseOpensGravitonOnlyWhenNobodyHasIt(): void
    {
        $this->assertSame(
            FeatCatalog::STATUS_OPEN,
            FeatCatalog::initialStatus(FeatCatalog::FIRST_GRAVITON, false, false, false, true)
        );
        $this->assertSame(
            FeatCatalog::STATUS_UNKNOWN,
            FeatCatalog::initialStatus(FeatCatalog::FIRST_GRAVITON, false, true, false, false)
        );
    }

    public function testLiveUniverseOpensHyperspaceAndMoonFromState(): void
    {
        $this->assertSame(
            FeatCatalog::STATUS_OPEN,
            FeatCatalog::initialStatus(FeatCatalog::FIRST_HYPERSPACE, false, false, false, true)
        );
        $this->assertSame(
            FeatCatalog::STATUS_UNKNOWN,
            FeatCatalog::initialStatus(FeatCatalog::FIRST_MOON, false, false, false, true)
        );
        $this->assertSame(
            FeatCatalog::STATUS_OPEN,
            FeatCatalog::initialStatus(FeatCatalog::FIRST_MOON, false, false, false, false)
        );
    }

    public function testLiveUniverseKeepsEventFirstsUnknown(): void
    {
        $this->assertSame(
            FeatCatalog::STATUS_UNKNOWN,
            FeatCatalog::initialStatus(FeatCatalog::FIRST_SHIP, false, false, false, false)
        );
        $this->assertSame(
            FeatCatalog::STATUS_UNKNOWN,
            FeatCatalog::initialStatus(FeatCatalog::FIRST_DEATHSTAR, false, false, false, false)
        );
        $this->assertSame(
            FeatCatalog::STATUS_UNKNOWN,
            FeatCatalog::initialStatus(FeatCatalog::MOON_DESTRUCTION, false, false, false, false)
        );
    }

    public function testShipFeatStatusDependsOnOwnership(): void
    {
        $owned = [
            FeatCatalog::FIRST_CRUISER => false,
            FeatCatalog::FIRST_DEATHSTAR => true,
        ];
        $this->assertSame(
            FeatCatalog::STATUS_OPEN,
            FeatCatalog::initialStatus(FeatCatalog::FIRST_CRUISER, false, false, false, false, $owned)
        );
        $this->assertSame(
            FeatCatalog::STATUS_UNKNOWN,
            FeatCatalog::initialStatus(FeatCatalog::FIRST_DEATHSTAR, false, false, false, false, $owned)
        );
    }
}
