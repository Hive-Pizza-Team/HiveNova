<?php

declare(strict_types=1);

use HiveNova\Core\AdminMultiIpList;
use PHPUnit\Framework\TestCase;

final class AdminMultiIpListTest extends TestCase
{
	public function testOwnerLabelReturnsUsernameWhenPresent(): void
	{
		$this->assertSame(
			'Alice',
			AdminMultiIpList::ownerLabel('Alice', 42, 'Deleted / unknown (#%s)')
		);
	}

	public function testOwnerLabelTrimsWhitespace(): void
	{
		$this->assertSame(
			'Bob',
			AdminMultiIpList::ownerLabel('  Bob  ', 7, 'Deleted / unknown (#%s)')
		);
	}

	public function testOwnerLabelFallsBackForEmptyUsername(): void
	{
		$this->assertSame(
			'Deleted / unknown (#99)',
			AdminMultiIpList::ownerLabel('', 99, 'Deleted / unknown (#%s)')
		);
	}

	public function testOwnerLabelFallsBackForNullUsername(): void
	{
		$this->assertSame(
			'Deleted / unknown (#12)',
			AdminMultiIpList::ownerLabel(null, 12, 'Deleted / unknown (#%s)')
		);
	}

	public function testGroupByIpResolvesOwnersAndFormatsTimes(): void
	{
		$rows = [
			[
				'id' => 1,
				'username' => 'Alice',
				'email' => 'a@example.com',
				'register_time' => 100,
				'onlinetime' => 200,
				'user_lastip' => '1.2.3.4',
				'isKnown' => 0,
			],
			[
				'id' => 2,
				'username' => '',
				'email' => 'b@example.com',
				'register_time' => 110,
				'onlinetime' => 210,
				'user_lastip' => '1.2.3.4',
				'isKnown' => 5,
			],
			[
				'id' => 3,
				'username' => 'Carol',
				'email' => 'c@example.com',
				'register_time' => 120,
				'onlinetime' => 220,
				'user_lastip' => '9.9.9.9',
				'isKnown' => 0,
			],
		];

		$groups = AdminMultiIpList::groupByIp(
			$rows,
			static fn (int $ts): string => 't'.$ts,
			'Deleted / unknown (#%s)'
		);

		$this->assertSame(['1.2.3.4', '9.9.9.9'], array_keys($groups));
		$this->assertSame('Alice', $groups['1.2.3.4'][1]['username']);
		$this->assertSame('Deleted / unknown (#2)', $groups['1.2.3.4'][2]['username']);
		$this->assertSame(5, $groups['1.2.3.4'][2]['isKnown']);
		$this->assertSame('t100', $groups['1.2.3.4'][1]['register_time']);
		$this->assertSame('t210', $groups['1.2.3.4'][2]['onlinetime']);
		$this->assertSame('Carol', $groups['9.9.9.9'][3]['username']);
	}

	public function testParseToggleActionAcceptsKnownAndUnknownWithPositiveId(): void
	{
		$this->assertSame(
			['action' => 'known', 'userId' => 79],
			AdminMultiIpList::parseToggleAction(['action' => 'known', 'id' => '79'])
		);
		$this->assertSame(
			['action' => 'unknown', 'userId' => 12],
			AdminMultiIpList::parseToggleAction(['action' => ' unknown ', 'id' => 12])
		);
	}

	public function testParseToggleActionRejectsInvalidActionOrId(): void
	{
		$this->assertNull(AdminMultiIpList::parseToggleAction([]));
		$this->assertNull(AdminMultiIpList::parseToggleAction(['action' => 'delete', 'id' => 5]));
		$this->assertNull(AdminMultiIpList::parseToggleAction(['action' => 'known', 'id' => 0]));
		$this->assertNull(AdminMultiIpList::parseToggleAction(['action' => 'unknown', 'id' => -3]));
	}

	public function testMultiIpsTemplatePostsToggleWithAdminCsrf(): void
	{
		$tpl = (string) file_get_contents(__DIR__ . '/../../styles/templates/adm/MultiIPs.tpl');
		$this->assertStringContainsString('method="post"', $tpl);
		$this->assertStringContainsString('name="admin_csrf"', $tpl);
		$this->assertStringNotContainsString('action=known&amp;id=', $tpl);
		$this->assertStringNotContainsString('action=unknown&amp;id=', $tpl);
	}
}
