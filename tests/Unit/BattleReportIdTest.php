<?php

use HiveNova\Core\BattleReportId;
use PHPUnit\Framework\TestCase;

class BattleReportIdTest extends TestCase
{
	public function testGenerateReturns22CharBase64Url(): void
	{
		$id = BattleReportId::generate();

		$this->assertSame(BattleReportId::NEW_ID_LENGTH, strlen($id));
		$this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $id);
		$this->assertTrue(BattleReportId::isValid($id));
	}

	public function testGenerateProducesDistinctIds(): void
	{
		$this->assertNotSame(BattleReportId::generate(), BattleReportId::generate());
	}

	public function testIsValidAcceptsLegacyHexId(): void
	{
		$this->assertTrue(BattleReportId::isValid('1002c6a3ab8aff2c6faeb1fbb6a9320b'));
	}

	public function testIsValidRejectsInvalidIds(): void
	{
		$this->assertFalse(BattleReportId::isValid(''));
		$this->assertFalse(BattleReportId::isValid('short'));
		$this->assertFalse(BattleReportId::isValid('1002c6a3ab8aff2c6faeb1fbb6a9320!'));
		$this->assertFalse(BattleReportId::isValid(str_repeat('a', 31)));
	}
}
