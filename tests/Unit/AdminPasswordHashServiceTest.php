<?php

declare(strict_types=1);

use HiveNova\Core\AdminPasswordHashService;
use PHPUnit\Framework\TestCase;

class AdminPasswordHashServiceTest extends TestCase
{
	public function testGetWithEmptyDefaultDoesNotHash(): void
	{
		$this->assertSame('', AdminPasswordHashService::previewHash('', false));
	}

	public function testEmptySubmitDoesNotHash(): void
	{
		$this->assertSame('', AdminPasswordHashService::previewHash('', true));
	}

	public function testUnsubmittedNonEmptyValueDoesNotHash(): void
	{
		$this->assertSame('', AdminPasswordHashService::previewHash('secret', false));
	}

	public function testSubmittedPasswordReturnsBcryptHash(): void
	{
		$hash = AdminPasswordHashService::previewHash('secret', true);

		$this->assertNotSame('', $hash);
		$this->assertTrue(password_verify('secret', $hash));
		$this->assertStringStartsWith('$2y$13$', $hash);
	}
}
