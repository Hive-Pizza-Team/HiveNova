<?php

declare(strict_types=1);

use HiveNova\Core\Language;
use HiveNova\Core\Template;
use PHPUnit\Framework\TestCase;

/**
 * Trade mission form (fleet step 2) and send confirmation (step 3): the allowed
 * price range, listing fee and max hold time are shown to the seller.
 */
class MarketOfferFormTemplateTest extends TestCase
{
	private function render(string $tpl, array $vars): string
	{
		$cwd = getcwd();
		chdir(ROOT_PATH);
		try {
			$lng = new Language('en');
			$lng->includeData(['L18N', 'INGAME', 'TECH', 'FLEET']);
		} finally {
			chdir($cwd);
		}

		$smarty = new Template();
		$smarty->setCaching(\Smarty::CACHING_OFF);
		$smarty->setForceCompile(true);
		$dir = sys_get_temp_dir() . '/hivenova-market-offer-tpl-' . getmypid() . '/';
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}
		$smarty->setCompileDir($dir);
		$smarty->setTemplateDir(ROOT_PATH . 'styles/templates/game/');
		$smarty->assign($vars + ['LNG' => $lng, 'dpath' => './styles/theme/hive/']);
		$prev = error_reporting(error_reporting() & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
		try {
			return $smarty->fetch($tpl);
		} finally {
			error_reporting($prev);
		}
	}

	public function testTradeFormShowsRangeFeeAndHoldLimit(): void
	{
		$html = $this->render('page.fleetStep2.default.tpl', [
			'token' => 't', 'targetInfo' => ['label' => ''], 'targetGalaxy' => 1, 'targetSystem' => 2, 'targetPlanet' => 17,
			'MissionSelector' => [16], 'mission' => 16, 'StaySelector' => [1 => 1, 24 => 24], 'Exchange' => true,
			'consumption' => '10', 'fleetdata' => ['fleetroom' => '100', 'consumption' => '10'],
			'showExpeditionStance' => false, 'fl_dm_alert_message' => '',
			'marketOffer' => ['ratio' => '4,2,1', 'shipValue' => '45000', 'bandMin' => 0.5, 'bandMax' => 2.0, 'feeRate' => 0.01, 'maxHold' => 24],
		]);
		$this->assertStringContainsString('id="marketOffer"', $html);
		$this->assertStringContainsString('data-ratio="4,2,1"', $html);
		$this->assertStringContainsString('data-ship-value="45000"', $html);
		$this->assertStringContainsString('data-band-min="0.5"', $html);
		$this->assertStringContainsString('data-band-max="2"', $html);
		$this->assertStringContainsString('data-fee-rate="0.01"', $html);
		$this->assertStringContainsString('data-range-text="Allowed price: %1$s – %2$s %3$s"', $html);
		$this->assertStringContainsString('Offers wait in the trading zone for at most 24 hours.', $html);
		$this->assertStringContainsString('id="marketOfferFee"', $html);
	}

	public function testSendConfirmationShowsListingFeeOnlyWhenCharged(): void
	{
		$base = [
			'targetMission' => 16, 'distance' => 1000, 'MaxFleetSpeed' => 5000, 'consumption' => 10,
			'from' => '1:2:3', 'destination' => '[1:2:17]', 'fleetStartTime' => '', 'fleetEndTime' => '', 'FleetList' => [203 => 2],
		];
		$html = $this->render('page.fleetStep3.default.tpl', $base + ['listingFee' => 1450]);
		$this->assertStringContainsString('Listing fee', $html);
		$this->assertStringContainsString('1.450', $html);

		$html = $this->render('page.fleetStep3.default.tpl', $base + ['listingFee' => 0]);
		$this->assertStringNotContainsString('Listing fee', $html);
	}

	public function testMarketOfferScriptIsFingerprinted(): void
	{
		$this->assertContains('scripts/game/market-offer.js', \HiveNova\Core\AssetRevision::fingerprintPaths());
	}
}
