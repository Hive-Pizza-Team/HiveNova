<?php

declare(strict_types=1);

use HiveNova\Core\ReferralCaptureService;
use PHPUnit\Framework\TestCase;

class ReferralSettingsUiTest extends TestCase
{
	public function test_settings_share_url_when_referrals_enabled(): void
	{
		$this->assertSame(
			'https://moon.hive.pizza/uni1/index.php?ref=68',
			ReferralCaptureService::settingsShareUrl(1, 68, 'https://moon.hive.pizza/uni1/')
		);
	}

	public function test_settings_share_url_hidden_when_referrals_disabled(): void
	{
		$this->assertSame(
			'',
			ReferralCaptureService::settingsShareUrl(0, 68, 'https://moon.hive.pizza/')
		);
		$this->assertSame('', ReferralCaptureService::settingsShareUrl(1, 0, 'https://moon.hive.pizza/'));
	}

	public function test_settings_template_shows_referral_section_when_enabled(): void
	{
		$tpl = file_get_contents(__DIR__ . '/../../styles/templates/game/page.settings.default.tpl');
		$page = file_get_contents(__DIR__ . '/../../includes/pages/game/ShowSettingsPage.php');

		$this->assertIsString($tpl);
		$this->assertIsString($page);
		$this->assertStringContainsString('{if $ref_active && $referralLink}', $tpl);
		$this->assertStringContainsString('id="referral-link"', $tpl);
		$this->assertStringContainsString('settingsShareUrl', $page);
	}

	public function test_overview_shows_recruits_when_referrals_enabled(): void
	{
		$tpl = file_get_contents(__DIR__ . '/../../styles/templates/game/page.overview.default.tpl');

		$this->assertIsString($tpl);
		$this->assertStringContainsString('{if $ref_active}', $tpl);
		$this->assertStringContainsString('{$LNG.ov_reflink}', $tpl);
		$this->assertStringContainsString('index.php?ref={$userid}', $tpl);
		$this->assertStringContainsString('{$LNG.ov_noreflink}', $tpl);
	}

	public function test_register_template_explains_inactive_referral(): void
	{
		$tpl = file_get_contents(__DIR__ . '/../../styles/templates/login/page.register.default.tpl');

		$this->assertIsString($tpl);
		$this->assertStringContainsString('reg-referral-inactive', $tpl);
		$this->assertStringContainsString('{$LNG.registerReferralInactive}', $tpl);
		$this->assertStringContainsString('name="referralID"', $tpl);
	}
}
