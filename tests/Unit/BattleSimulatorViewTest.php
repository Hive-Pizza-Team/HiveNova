<?php

use HiveNova\Core\BattleSimulatorView;
use PHPUnit\Framework\TestCase;

class BattleSimulatorViewTest extends TestCase
{
	public function testFleetIdsIncludeFlyableAndBattleinputShips(): void
	{
		$isFlyable = static fn(int $id): bool => in_array($id, [202, 204], true);
		$battle = [
			0 => [
				1 => [203 => 5, 401 => 10],
			],
		];

		$out = BattleSimulatorView::fleetIdsForForm([202, 203, 204, 210], $isFlyable, $battle);

		$this->assertSame([202, 203, 204], $out);
	}

	public function testDefenseIdsKeepFullListWhenAllZeroOnCompact(): void
	{
		$out = BattleSimulatorView::defenseIdsForForm([401, 402], [], true);
		$this->assertSame([401, 402], $out);
	}

	public function testDefenseIdsOmitZeroRowsOnCompactWhenSomePresent(): void
	{
		$battle = [0 => [1 => [401 => 3]]];
		$out = BattleSimulatorView::defenseIdsForForm([401, 402, 403], $battle, true);
		$this->assertSame([401], $out);
	}

	public function testSlotsToRenderUsesBattleinputWidth(): void
	{
		$this->assertSame(1, BattleSimulatorView::slotsToRender([], 1));
		$this->assertSame(3, BattleSimulatorView::slotsToRender([0 => [], 1 => [], 2 => []], 1));
		$this->assertSame(2, BattleSimulatorView::slotsToRender([0 => []], 2));
		$this->assertSame(10, BattleSimulatorView::slotsToRender([], 99));
	}
}
