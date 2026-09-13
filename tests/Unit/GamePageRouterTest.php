<?php

declare(strict_types=1);

use HiveNova\Core\GamePageRouter;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class GamePageRouterTest extends TestCase
{
	public function test_resolves_empire_alias_to_imperium(): void
	{
		$this->assertSame('imperium', GamePageRouter::resolve('empire'));
		$this->assertSame('imperium', GamePageRouter::resolve('Empire'));
		$this->assertSame('imperium', GamePageRouter::resolve('EMPIRE'));
	}

	public function test_strips_path_metacharacters_before_alias(): void
	{
		$this->assertSame('imperium', GamePageRouter::resolve('em.pire'));
		$this->assertSame('overview', GamePageRouter::resolve('over_view'));
	}

	public function test_leaves_unknown_pages_intact(): void
	{
		$this->assertSame('overview', GamePageRouter::resolve('overview'));
		$this->assertSame('imperium', GamePageRouter::resolve('imperium'));
		$this->assertSame('buildings', GamePageRouter::resolve('buildings'));
	}
}
