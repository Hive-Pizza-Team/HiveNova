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

	public function test_resolves_faq_alias_to_questions(): void
	{
		$this->assertSame('questions', GamePageRouter::resolve('faq'));
		$this->assertSame('questions', GamePageRouter::resolve('FAQ'));
		$this->assertSame('questions', GamePageRouter::resolve('Faq'));
	}

	public function test_resolves_officer_aliases_to_officier(): void
	{
		$this->assertSame('officier', GamePageRouter::resolve('officers'));
		$this->assertSame('officier', GamePageRouter::resolve('officer'));
		$this->assertSame('officier', GamePageRouter::resolve('Officers'));
		$this->assertSame('officier', GamePageRouter::resolve('OFFICER'));
	}

	public function test_strips_path_metacharacters_before_alias(): void
	{
		$this->assertSame('imperium', GamePageRouter::resolve('em.pire'));
		$this->assertSame('overview', GamePageRouter::resolve('over_view'));
		$this->assertSame('questions', GamePageRouter::resolve('f.aq'));
		$this->assertSame('officier', GamePageRouter::resolve('off_icers'));
	}

	public function test_leaves_unknown_pages_intact(): void
	{
		$this->assertSame('overview', GamePageRouter::resolve('overview'));
		$this->assertSame('imperium', GamePageRouter::resolve('imperium'));
		$this->assertSame('buildings', GamePageRouter::resolve('buildings'));
		$this->assertSame('questions', GamePageRouter::resolve('questions'));
		$this->assertSame('officier', GamePageRouter::resolve('officier'));
	}
}
