<?php

use HiveNova\Core\Config;
use HiveNova\Cronjob\ReferralCronJob;

use PHPUnit\Framework\TestCase;

class ReferralCronjobTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ROOT_UNI')) {
            define('ROOT_UNI', 1);
        }

        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    public function test_run_returns_null_when_referrals_disabled(): void
    {
        Config::setInstance(new Config(['uni' => 1, 'ref_active' => 0]), 1);

        $cron = new ReferralCronJob();
        $this->assertNull($cron->run());
    }
}
