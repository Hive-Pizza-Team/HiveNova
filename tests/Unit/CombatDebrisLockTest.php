<?php

use PHPUnit\Framework\TestCase;

class CombatDebrisLockTest extends TestCase
{
	public function testAttackMissionLocksDebrisRowsBeforeUpdate(): void
	{
		$source = file_get_contents(__DIR__ . '/../../includes/classes/missions/MissionCaseCombat.php');
		$this->assertIsString($source);
		$this->assertStringContainsString('der_metal, der_crystal FROM %%PLANETS%%', $source);
		$this->assertStringContainsString('FOR UPDATE', $source);
		$this->assertStringContainsString('beginTransaction', $source);
	}
}
