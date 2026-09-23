<?php

declare(strict_types=1);

use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\ReferralActivationService;
use PHPUnit\Framework\TestCase;

class ReferralActivationServiceTest extends TestCase
{
	public function test_skip_verify_activation_writes_ref_id_and_ref_bonus(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->once())
			->method('update')
			->with(
				$this->stringContains('`ref_bonus` = 1'),
				[
					':referralId' => 68,
					':userId'     => 74,
				]
			);

		$attached = (new ReferralActivationService())->attachFromPending($db, 74, [
			'referralID' => '68',
			'userName'   => 'TideFen98',
		]);

		$this->assertTrue($attached);
	}

	public function test_attach_skips_when_referral_id_missing(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->never())->method('update');

		$service = new ReferralActivationService();

		$this->assertFalse($service->attachFromPending($db, 74, []));
		$this->assertFalse($service->attachFromPending($db, 74, ['referralID' => 0]));
		$this->assertFalse($service->attach($db, 0, 68));
		$this->assertFalse($service->attach($db, 74, 0));
	}

	public function test_skip_verify_redirects_to_vertify_which_attaches_referral(): void
	{
		$register = file_get_contents(__DIR__ . '/../../includes/pages/login/ShowRegisterPage.php');
		$vertify = file_get_contents(__DIR__ . '/../../includes/pages/login/ShowVertifyPage.php');

		$this->assertIsString($register);
		$this->assertIsString($vertify);
		$this->assertStringContainsString('user_valid == 0', $register);
		$this->assertStringContainsString('page=vertify', $register);
		$this->assertStringContainsString('attachFromPending', $vertify);
	}
}
