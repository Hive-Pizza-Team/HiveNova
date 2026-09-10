<?php

use HiveNova\Page\Login\ShowDisclaimerPage;
use PHPUnit\Framework\TestCase;

class ShowDisclaimerPageTest extends TestCase
{
	public function testConstructorIsPublicLikeOtherLoginPages(): void
	{
		$ctor = new \ReflectionMethod(ShowDisclaimerPage::class, '__construct');
		$this->assertTrue($ctor->isPublic());
	}
}
