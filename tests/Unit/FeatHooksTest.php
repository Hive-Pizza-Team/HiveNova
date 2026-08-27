<?php

declare(strict_types=1);

use HiveNova\Core\Config;
use HiveNova\Core\FeatCatalog;
use HiveNova\Core\FeatHooks;
use HiveNova\Core\FeatService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class FeatHooksTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    /** @var mixed */
    private $savedReslist;

    /** @var mixed */
    private $savedResource;

    protected function setUp(): void
    {
        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);
        $this->savedReslist = $GLOBALS['reslist'] ?? null;
        $this->savedResource = $GLOBALS['resource'] ?? null;
        $GLOBALS['reslist'] = [
            'fleet'   => [202, 204, FeatCatalog::DEATHSTAR_ID],
            'defense' => [401, 402],
        ];
        $GLOBALS['resource'] = [
            401 => 'misil_launcher',
            402 => 'small_laser',
            FeatCatalog::DEATHSTAR_ID => 'dearth_star',
        ];
        $this->fake->achievement->users[7] = [
            'id' => 7,
            'username' => 'hook',
            'lang' => 'en',
            'universe' => 1,
        ];
        Config::setInstance(new Config([
            'uni' => 1,
            'moduls' => implode(';', array_fill(0, MODULE_AMOUNT, 1)),
            'feat_tracking_from_start' => 0,
            'feat_banner_key' => '',
            'feat_banner_user_id' => 0,
            'feat_banner_at' => 0,
            'discord_feat_webhook' => '',
        ]), 1);
    }

    protected function tearDown(): void
    {
        if (is_array($this->savedReslist)) {
            $GLOBALS['reslist'] = $this->savedReslist;
        } else {
            unset($GLOBALS['reslist']);
        }
        if (is_array($this->savedResource)) {
            $GLOBALS['resource'] = $this->savedResource;
        } else {
            unset($GLOBALS['resource']);
        }
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    private function open(string $key): void
    {
        $this->fake->achievement->featStates['1:' . $key] = [
            'status' => FeatCatalog::STATUS_OPEN,
            'winner_id' => 0,
            'claimed_at' => 0,
        ];
    }

    public function testBuildCompletedIgnoresInvalidUser(): void
    {
        FeatHooks::afterBuildCompleted([202 => 1], ['id' => 0, 'universe' => 1]);
        $this->assertSame([], $this->fake->achievement->featClaims);
    }

    public function testBuildCompletedClaimsTechFleetAndDeathstar(): void
    {
        $this->open(FeatCatalog::FIRST_GRAVITON);
        $this->open(FeatCatalog::FIRST_HYPERSPACE);
        $this->open(FeatCatalog::FIRST_DEATHSTAR);
        $this->open(FeatCatalog::FIRST_LIGHT_FIGHTER);
        $this->open(FeatCatalog::FIRST_SHIP);
        FeatHooks::afterBuildCompleted(
            [
                FeatCatalog::GRAVITON_TECH_ID => 1,
                FeatCatalog::HYPERSPACE_TECH_ID => 1,
                FeatCatalog::DEATHSTAR_ID => 1,
                202 => 0,
                204 => 2,
            ],
            ['id' => 7, 'universe' => 1]
        );
        $this->assertArrayHasKey('1:' . FeatCatalog::FIRST_GRAVITON, $this->fake->achievement->featClaims);
        $this->assertArrayHasKey('1:' . FeatCatalog::FIRST_HYPERSPACE, $this->fake->achievement->featClaims);
        $this->assertArrayHasKey('1:' . FeatCatalog::FIRST_DEATHSTAR, $this->fake->achievement->featClaims);
        $this->assertArrayHasKey('1:' . FeatCatalog::FIRST_LIGHT_FIGHTER, $this->fake->achievement->featClaims);
        $this->assertArrayHasKey('1:' . FeatCatalog::FIRST_SHIP, $this->fake->achievement->featClaims);
        $this->assertArrayNotHasKey('1:' . FeatCatalog::FIRST_SMALL_CARGO, $this->fake->achievement->featClaims);
    }

    public function testBuildCompletedClaimsSpecialShipFeat(): void
    {
        $this->open(FeatCatalog::FIRST_BLACK_MOON);
        $this->open(FeatCatalog::FIRST_SHIP);
        FeatHooks::afterBuildCompleted([216 => 1], ['id' => 7, 'universe' => 1]);
        $this->assertArrayHasKey('1:' . FeatCatalog::FIRST_BLACK_MOON, $this->fake->achievement->featClaims);
        $this->assertArrayHasKey('1:' . FeatCatalog::FIRST_SHIP, $this->fake->achievement->featClaims);
    }

    public function testCombatClaimsRaidDefendAndDeathstarFeats(): void
    {
        $this->open(FeatCatalog::RAID_DEFENSES);
        $this->open(FeatCatalog::DEFEND_100_SHIPS);
        $this->open(FeatCatalog::LOSE_DEATHSTAR);
        $this->open(FeatCatalog::DEFEAT_DEATHSTAR);
        FeatHooks::afterCombat([7 => 'a'], [8 => 'd'], true, false, 1, 10, 5, false, true);
        $this->assertArrayHasKey('1:' . FeatCatalog::RAID_DEFENSES, $this->fake->achievement->featClaims);
        $this->assertArrayHasKey('1:' . FeatCatalog::DEFEAT_DEATHSTAR, $this->fake->achievement->featClaims);

        FeatHooks::afterCombat([9 => 'a'], [7 => 'd'], false, true, 1, 100, 0, true, false);
        $this->assertArrayHasKey('1:' . FeatCatalog::DEFEND_100_SHIPS, $this->fake->achievement->featClaims);
        $this->assertArrayHasKey('1:' . FeatCatalog::LOSE_DEATHSTAR, $this->fake->achievement->featClaims);
    }

    public function testMoonAndAbandonHooks(): void
    {
        $this->open(FeatCatalog::FIRST_MOON);
        $this->open(FeatCatalog::GIVE_MOON);
        $this->open(FeatCatalog::MOON_DESTRUCTION);
        $this->open(FeatCatalog::FIRST_COLONY);
        $this->open(FeatCatalog::FIRST_EXPEDITION);
        $this->open(FeatCatalog::ABANDON_PLANET);
        $this->open(FeatCatalog::ABANDON_HOME);
        FeatHooks::afterMoonCreated(1, 7, 8);
        FeatHooks::afterMoonDestroyed(1, 7);
        FeatHooks::afterColonisation(1, 7);
        FeatHooks::afterExpedition(1, 7);
        FeatHooks::afterAbandonPlanet(1, 7, true);
        $this->assertArrayHasKey('1:' . FeatCatalog::FIRST_MOON, $this->fake->achievement->featClaims);
        $this->assertArrayHasKey('1:' . FeatCatalog::GIVE_MOON, $this->fake->achievement->featClaims);
        $this->assertArrayHasKey('1:' . FeatCatalog::MOON_DESTRUCTION, $this->fake->achievement->featClaims);
        $this->assertArrayHasKey('1:' . FeatCatalog::FIRST_COLONY, $this->fake->achievement->featClaims);
        $this->assertArrayHasKey('1:' . FeatCatalog::FIRST_EXPEDITION, $this->fake->achievement->featClaims);
        $this->assertArrayHasKey('1:' . FeatCatalog::ABANDON_PLANET, $this->fake->achievement->featClaims);
        $this->assertArrayHasKey('1:' . FeatCatalog::ABANDON_HOME, $this->fake->achievement->featClaims);
    }

    public function testFleetAndPlanetHelpers(): void
    {
        $this->assertSame(5, FeatHooks::attackerShipCount('204,3;212,10;202,2;'));
        $this->assertTrue(FeatHooks::fleetHasDeathstar('214,1;'));
        $this->assertFalse(FeatHooks::fleetHasDeathstar('202,4;'));
        $this->assertSame(7, FeatHooks::planetDefenseCount([
            'misil_launcher' => 3,
            'small_laser' => 4,
        ]));
        $this->assertTrue(FeatHooks::planetHasDeathstar(['dearth_star' => 2]));
        $this->assertFalse(FeatHooks::planetHasDeathstar([]));
    }
}
