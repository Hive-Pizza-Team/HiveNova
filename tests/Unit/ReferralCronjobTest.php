<?php

use HiveNova\Core\Config;
use HiveNova\Core\Universe;
use HiveNova\Cronjob\ReferralCronjob;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class ReferralCronjobFakeDatabase extends FakeDatabase
{
    /** @var list<array<string, mixed>> */
    public array $pendingReferrals = [];

    /** @var array<int, int> */
    public array $darkmatterByUser = [];

    /** @var array<int, int> */
    public array $clearedRefBonus = [];

    /** @var list<array<string, mixed>> */
    public array $messages = [];

    public function select($qry, array $params = [])
    {
        if (str_contains($qry, 'ref_bonus') && str_contains($qry, '%%STATPOINTS%%')) {
            return $this->pendingReferrals;
        }

        return parent::select($qry, $params);
    }

    public function update($qry, array $params = [])
    {
        if (str_contains($qry, 'darkmatter') && str_contains($qry, ':bonus')) {
            $userId = (int) $params[':userId'];
            $this->darkmatterByUser[$userId] = ($this->darkmatterByUser[$userId] ?? 0) + (int) $params[':bonus'];

            return true;
        }

        if (str_contains($qry, 'ref_bonus') && str_contains($qry, '`ref_bonus` = 0')) {
            $this->clearedRefBonus[(int) $params[':userId']] = 1;

            return true;
        }

        return parent::update($qry, $params);
    }

    public function insert($qry, array $params = [])
    {
        if (str_contains($qry, '%%MESSAGES%%')) {
            $this->messages[] = $params;

            return true;
        }

        return parent::insert($qry, $params);
    }
}

class ReferralCronjobTest extends TestCase
{
    use SwapDatabaseInstance;

    protected function setUp(): void
    {
        if (!defined('ROOT_UNI')) {
            define('ROOT_UNI', 1);
        }

        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    protected function tearDown(): void
    {
        $this->restoreDatabaseInstance();
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    public function test_run_skips_payout_when_universe_referrals_disabled(): void
    {
        Config::setInstance(new Config([
            'uni' => 1,
            'ref_active' => 0,
            'ref_bonus' => 1000,
            'ref_bonus_referee' => 500,
            'ref_minpoints' => 2000,
        ]), 1);

        $fake = new ReferralCronjobFakeDatabase();
        $fake->pendingReferrals = [$this->referralRow()];
        $this->swapDatabaseInstance($fake);

        $cron = new ReferralCronjob();
        $this->assertTrue($cron->run());
        $this->assertSame([], $fake->darkmatterByUser);
        $this->assertSame([], $fake->clearedRefBonus);
    }

    public function test_run_pays_referrer_and_recruit_when_threshold_met(): void
    {
        Config::setInstance(new Config([
            'uni' => 1,
            'ref_active' => 1,
            'ref_bonus' => 1000,
            'ref_bonus_referee' => 400,
            'ref_minpoints' => 2000,
        ]), 1);
        Universe::add(1);

        $fake = new ReferralCronjobFakeDatabase();
        $fake->pendingReferrals = [$this->referralRow(['total_points' => 2500])];
        $this->swapDatabaseInstance($fake);

        $cron = new ReferralCronjob();
        $this->assertTrue($cron->run());
        $this->assertSame(1000, $fake->darkmatterByUser[10] ?? 0);
        $this->assertSame(400, $fake->darkmatterByUser[20] ?? 0);
        $this->assertSame(1, $fake->clearedRefBonus[20] ?? 0);
        $this->assertCount(2, $fake->messages);
        $owners = array_column($fake->messages, ':userId');
        $this->assertEqualsCanonicalizing([10, 20], $owners);
    }

    public function test_run_does_not_pay_below_minpoints(): void
    {
        Config::setInstance(new Config([
            'uni' => 1,
            'ref_active' => 1,
            'ref_bonus' => 1000,
            'ref_bonus_referee' => 400,
            'ref_minpoints' => 2000,
        ]), 1);
        Universe::add(1);

        $fake = new ReferralCronjobFakeDatabase();
        $fake->pendingReferrals = [$this->referralRow(['total_points' => 1999])];
        $this->swapDatabaseInstance($fake);

        $cron = new ReferralCronjob();
        $this->assertTrue($cron->run());
        $this->assertSame([], $fake->darkmatterByUser);
        $this->assertSame([], $fake->clearedRefBonus);
    }

    public function test_run_skips_zero_recruit_bonus_but_pays_referrer(): void
    {
        Config::setInstance(new Config([
            'uni' => 1,
            'ref_active' => 1,
            'ref_bonus' => 800,
            'ref_bonus_referee' => 0,
            'ref_minpoints' => 2000,
        ]), 1);
        Universe::add(1);

        $fake = new ReferralCronjobFakeDatabase();
        $fake->pendingReferrals = [$this->referralRow(['total_points' => 2000])];
        $this->swapDatabaseInstance($fake);

        $cron = new ReferralCronjob();
        $this->assertTrue($cron->run());
        $this->assertSame([10 => 800], $fake->darkmatterByUser);
        $this->assertSame([20 => 1], $fake->clearedRefBonus);
        $this->assertCount(1, $fake->messages);
        $this->assertSame(10, (int) $fake->messages[0][':userId']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function referralRow(array $overrides = []): array
    {
        return array_merge([
            'username' => 'Recruit',
            'ref_id' => 10,
            'id' => 20,
            'recruit_lang' => 'en',
            'referrer_lang' => 'en',
            'universe' => 1,
            'total_points' => 2000,
        ], $overrides);
    }
}
