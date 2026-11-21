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

	public function test_attach_from_pending_writes_signup_src_with_referral(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->exactly(2))
			->method('update')
			->willReturnCallback(function (string $sql, array $params): int {
				static $call = 0;
				$call++;
				if ($call === 1) {
					$this->assertStringContainsString('`ref_bonus` = 1', $sql);
					$this->assertSame(68, $params[':referralId']);
					$this->assertSame(74, $params[':userId']);
				} else {
					$this->assertStringContainsString('`signup_src`', $sql);
					$this->assertSame('discord', $params[':signupSrc']);
					$this->assertSame(74, $params[':userId']);
				}

				return 1;
			});

		$attached = (new ReferralActivationService())->attachFromPending($db, 74, [
			'referralID'  => '68',
			'signup_src'  => 'Discord',
			'userName'    => 'TideFen98',
		]);

		$this->assertTrue($attached);
	}

	public function test_attach_from_pending_writes_signup_src_without_referral(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->once())
			->method('update')
			->with(
				$this->stringContains('`signup_src`'),
				[
					':signupSrc' => 'peakd',
					':userId'    => 74,
				]
			);

		$attached = (new ReferralActivationService())->attachFromPending($db, 74, [
			'referralID' => 0,
			'signup_src' => 'PeakD',
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
		$this->assertFalse($service->attachFromPending($db, 74, [
			'referralID' => 0,
			'signup_src' => '!!!',
		]));
		$this->assertFalse($service->attach($db, 0, 68));
		$this->assertFalse($service->attach($db, 74, 0));
		$this->assertFalse($service->attachSignupSource($db, 74, null));
		$this->assertFalse($service->attachSignupSource($db, 0, 'x'));
	}

	public function test_skip_verify_redirects_to_vertify_which_attaches_referral(): void
	{
		$register = file_get_contents(__DIR__ . '/../../includes/pages/login/ShowRegisterPage.php');
		$vertify = file_get_contents(__DIR__ . '/../../includes/pages/login/ShowVertifyPage.php');

		$this->assertIsString($register);
		$this->assertIsString($vertify);
		$this->assertStringContainsString('user_valid == 0', $register);
		$this->assertStringContainsString('page=vertify', $register);
		$this->assertStringContainsString('signup_src', $register);
		$this->assertStringContainsString('attachFromPending', $vertify);
		$this->assertStringContainsString('signup_src', $vertify);
	}
}
