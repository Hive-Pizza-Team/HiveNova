<?php

declare(strict_types=1);

use HiveNova\Core\FeatCatalog;
use PHPUnit\Framework\TestCase;

class FeatCatalogTest extends TestCase
{
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
}
