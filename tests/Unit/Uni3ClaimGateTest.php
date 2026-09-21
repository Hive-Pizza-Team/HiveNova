<?php

use HiveNova\Core\Uni3ClaimGate;

use PHPUnit\Framework\TestCase;

class Uni3ClaimGateTest extends TestCase
{
	private Uni3ClaimGate $gate;

	protected function setUp(): void
	{
		parent::setUp();
		$this->gate = new Uni3ClaimGate();
	}

	public function testPrizeNeedsHiveEntryAndPoints(): void
	{
		$this->assertTrue($this->gate->prizeEligible(true, true, 50, 50));
		$this->assertFalse($this->gate->prizeEligible(false, true, 50, 50));
		$this->assertFalse($this->gate->prizeEligible(true, false, 50, 50));
		$this->assertFalse($this->gate->prizeEligible(true, true, 49, 50));
		$this->assertTrue($this->gate->prizeLocked(false, true));
		$this->assertTrue($this->gate->prizeLocked(true, false));
		$this->assertFalse($this->gate->prizeLocked(true, true));
	}

	public function testEntryAfterCloseIsRejected(): void
	{
		$this->assertFalse($this->gate->acceptsEntryAfterClose());
	}

	public function testEmailPayoutsWaitForClaimAndKeychainAutoPays(): void
	{
		$this->assertSame(Uni3ClaimGate::PAYOUT_PENDING_CLAIM, $this->gate->payoutStatusForOrigin(Uni3ClaimGate::ORIGIN_EMAIL));
		$this->assertSame(Uni3ClaimGate::PAYOUT_PENDING, $this->gate->payoutStatusForOrigin(Uni3ClaimGate::ORIGIN_KEYCHAIN));
		$this->assertSame(Uni3ClaimGate::PAYOUT_PENDING, $this->gate->payoutStatusForOrigin('legacy'));
	}

	public function testClaimWindowIsSevenDaysAfterClose(): void
	{
		$closes = 1_000_000;
		$this->assertFalse($this->gate->claimWindowOpen($closes, $closes - 1));
		$this->assertTrue($this->gate->claimWindowOpen($closes, $closes));
		$this->assertTrue($this->gate->claimWindowOpen($closes, $closes + Uni3ClaimGate::CLAIM_WINDOW_SECONDS - 1));
		$this->assertFalse($this->gate->claimWindowOpen($closes, $closes + Uni3ClaimGate::CLAIM_WINDOW_SECONDS));
		$this->assertTrue($this->gate->claimWindowExpired($closes, $closes + Uni3ClaimGate::CLAIM_WINDOW_SECONDS));
		$this->assertSame(7, Uni3ClaimGate::CLAIM_WINDOW_DAYS);
		$this->assertSame(7 * 86400, Uni3ClaimGate::CLAIM_WINDOW_SECONDS);
	}

	public function testOneHivePerSeatRejectsADifferentUser(): void
	{
		$this->assertTrue($this->gate->evaluateLink(4, null)['ok']);
		$this->assertSame('already', $this->gate->evaluateLink(4, 4)['reason']);
		$taken = $this->gate->evaluateLink(4, 9);
		$this->assertFalse($taken['ok']);
		$this->assertSame('hive_taken', $taken['reason']);
	}

	public function testBannerIsStickyNotPerClickAndClaimIsHard(): void
	{
		$shown = $this->gate->banner(true, true, false, false);
		$this->assertTrue($shown['show']);
		$this->assertTrue($shown['dismissible']);
		$this->assertSame('locked', $shown['mode']);

		$hidden = $this->gate->banner(true, true, false, true);
		$this->assertFalse($hidden['show']);

		$hard = $this->gate->banner(true, false, true, true);
		$this->assertTrue($hard['show']);
		$this->assertFalse($hard['dismissible']);
		$this->assertSame('claim', $hard['mode']);

		$this->assertFalse($this->gate->banner(false, true, true, false)['show']);
	}

	public function testMedalMetadataIsPrestigeOnly(): void
	{
		$meta = $this->gate->medalMetadata(3, 2, 'AliceAAA', 'Nova', 80, 'pending_claim');
		$this->assertSame('season_medal', $meta['kind']);
		$this->assertSame('participant', $meta['tier']);
		$this->assertSame('aliceaaa', $meta['hive_user']);
		$this->assertFalse($meta['transferable']);
		$this->assertSame('none', $meta['chain']);
		$this->assertArrayNotHasKey('attack', $meta);
		$this->assertArrayNotHasKey('fleet_power', $meta);
		$this->assertArrayNotHasKey('resource_multiplier', $meta);
	}
}
