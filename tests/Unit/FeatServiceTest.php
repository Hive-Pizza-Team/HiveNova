<?php

declare(strict_types=1);

use HiveNova\Core\Config;
use HiveNova\Core\DiscordWebhookService;
use HiveNova\Core\FeatCatalog;
use HiveNova\Core\FeatService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class FeatServiceTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fake;

    protected function setUp(): void
    {
        $this->fake = new FakeDatabase();
        $this->swapDatabaseInstance($this->fake);
        if (!defined('TIMESTAMP')) {
            define('TIMESTAMP', 1_700_000_000);
        }
        $this->fake->achievement->users[1] = [
            'id' => 1,
            'username' => 'alice',
            'lang' => 'en',
            'universe' => 1,
        ];
        $this->fake->achievement->users[2] = [
            'id' => 2,
            'username' => 'bob',
            'lang' => 'en',
            'universe' => 1,
        ];
        $this->fake->achievement->featStates['1:' . FeatCatalog::FIRST_SHIP] = [
            'status' => FeatCatalog::STATUS_OPEN,
            'winner_id' => 0,
            'claimed_at' => 0,
        ];
        $this->fake->achievement->addAchievement([
            'id' => 90,
            'key' => FeatCatalog::FIRST_SHIP,
            'universe' => 1,
            'hof_only' => 1,
            'points' => 0,
            'reward_type' => 'none',
            'reward_amount' => 0,
            'category' => 'fleet',
            'name_key' => 'feat_first_ship_name',
            'desc_key' => 'feat_first_ship_desc',
            'trigger_type' => 'universe_first',
            'trigger_params' => '{}',
            'celebration_tier' => 'normal',
            'hidden' => 0,
            'active' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        DiscordWebhookService::setPoster(null);
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    public function testFirstClaimWinsSecondIsSilent(): void
    {
        $this->assertTrue(FeatService::tryClaim(1, FeatCatalog::FIRST_SHIP, 1, TIMESTAMP));
        $this->assertFalse(FeatService::tryClaim(1, FeatCatalog::FIRST_SHIP, 2, TIMESTAMP + 1));
        $this->assertSame(1, $this->fake->achievement->featClaims['1:' . FeatCatalog::FIRST_SHIP]['user_id']);
        $this->assertTrue(isset($this->fake->achievement->unlocked['1:90']));
    }

    public function testUnknownStateDoesNotClaim(): void
    {
        $this->fake->achievement->featStates['1:' . FeatCatalog::FIRST_SHIP]['status'] = FeatCatalog::STATUS_UNKNOWN;
        $this->assertFalse(FeatService::tryClaim(1, FeatCatalog::FIRST_SHIP, 1, TIMESTAMP));
        $this->assertArrayNotHasKey('1:' . FeatCatalog::FIRST_SHIP, $this->fake->achievement->featClaims);
    }

    public function testRejectsInvalidUserAndUnknownKey(): void
    {
        $this->assertFalse(FeatService::tryClaim(1, FeatCatalog::FIRST_SHIP, 0, TIMESTAMP));
        $this->assertFalse(FeatService::tryClaim(1, 'not_a_feat', 1, TIMESTAMP));
    }

    public function testListForUniverseFillsCatalogOrder(): void
    {
        $this->fake->achievement->featStates['1:' . FeatCatalog::FIRST_SHIP] = [
            'status' => FeatCatalog::STATUS_CLAIMED,
            'winner_id' => 1,
            'claimed_at' => 99,
        ];
        $list = FeatService::listForUniverse(1);
        $this->assertSame(FeatCatalog::keys(), array_column($list, 'feat_key'));
        $ship = $list[0];
        $this->assertSame(FeatCatalog::STATUS_CLAIMED, $ship['status']);
        $this->assertSame('alice', $ship['username']);
        $this->assertSame(99, $ship['claimed_at']);
        $this->assertFalse($ship['hidden']);
        $blackMoon = null;
        foreach ($list as $row) {
            if ($row['feat_key'] === FeatCatalog::FIRST_BLACK_MOON) {
                $blackMoon = $row;
                break;
            }
        }
        $this->assertNotNull($blackMoon);
        $this->assertTrue($blackMoon['hidden']);
    }

    public function testSeedUniverseOpensAllWhenTrackingFromStart(): void
    {
        $this->fake->achievement->featStates = [];
        FeatService::seedUniverse(2, true);
        foreach (FeatCatalog::keys() as $key) {
            $this->assertSame(
                FeatCatalog::STATUS_OPEN,
                $this->fake->achievement->featStates['2:' . $key]['status']
            );
        }
        $hiddenKeys = [];
        foreach ($this->fake->achievement->achievementDefinitions as $def) {
            if ((int) ($def['universe'] ?? 0) === 2 && (int) ($def['hidden'] ?? 0) === 1) {
                $hiddenKeys[] = $def['key'];
            }
        }
        $this->assertContains(FeatCatalog::FIRST_BLACK_MOON, $hiddenKeys);
        $this->assertContains(FeatCatalog::FIRST_AVATAR, $hiddenKeys);
    }

    public function testEnsureSeededInsertsMissingShipFeats(): void
    {
        $GLOBALS['resource'] = [
            202 => 'small_ship_cargo',
            214 => 'dearth_star',
            216 => 'lune_noir',
        ];
        $this->fake->achievement->featStates = [
            '1:' . FeatCatalog::FIRST_SHIP => [
                'status' => FeatCatalog::STATUS_OPEN,
                'winner_id' => 0,
                'claimed_at' => 0,
            ],
        ];
        $this->fake->achievement->planetShipTotals = [
            'small_ship_cargo' => 5,
            'dearth_star' => 0,
            'lune_noir' => 0,
        ];
        Config::setInstance(new Config([
            'uni' => 1,
            'feat_tracking_from_start' => 0,
        ]), 1);

        FeatService::ensureSeeded(1);

        $this->assertArrayHasKey('1:' . FeatCatalog::FIRST_SMALL_CARGO, $this->fake->achievement->featStates);
        $this->assertSame(
            FeatCatalog::STATUS_UNKNOWN,
            $this->fake->achievement->featStates['1:' . FeatCatalog::FIRST_SMALL_CARGO]['status']
        );
        $this->assertArrayHasKey('1:' . FeatCatalog::FIRST_DEATHSTAR, $this->fake->achievement->featStates);
        $this->assertSame(
            FeatCatalog::STATUS_OPEN,
            $this->fake->achievement->featStates['1:' . FeatCatalog::FIRST_DEATHSTAR]['status']
        );
        $this->assertArrayHasKey('1:' . FeatCatalog::FIRST_BLACK_MOON, $this->fake->achievement->featStates);
        $this->assertSame(
            FeatCatalog::STATUS_OPEN,
            $this->fake->achievement->featStates['1:' . FeatCatalog::FIRST_BLACK_MOON]['status']
        );
    }

    public function testUnlockWithoutAchievementRowIsSilent(): void
    {
        $this->fake->achievement->featStates['1:' . FeatCatalog::FIRST_COLONY] = [
            'status' => FeatCatalog::STATUS_OPEN,
            'winner_id' => 0,
            'claimed_at' => 0,
        ];
        $this->assertTrue(FeatService::tryClaim(1, FeatCatalog::FIRST_COLONY, 1, TIMESTAMP));
    }

    public function testBroadcastWritesBannerInboxAndFeed(): void
    {
        Config::setInstance(new Config([
            'uni' => 1,
            'feat_banner_key' => '',
            'feat_banner_user_id' => 0,
            'feat_banner_at' => 0,
            'discord_feat_webhook' => '',
            'feat_tracking_from_start' => 0,
        ]), 1);
        $posts = [];
        DiscordWebhookService::setPoster(function (string $url, string $json) use (&$posts): int {
            $posts[] = $json;
            return 204;
        });

        $this->assertTrue(FeatService::tryClaim(1, FeatCatalog::FIRST_SHIP, 1, TIMESTAMP));
        $this->assertNotEmpty($this->fake->achievement->messages);
        $this->assertNotEmpty($this->fake->achievement->universeEvents);
        $this->assertSame('feat', $this->fake->achievement->universeEvents[0]['event_type']);
    }
}
