<?php

declare(strict_types=1);

use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\RegisterUsernameAvailability;
use PHPUnit\Framework\TestCase;

class RegisterUsernameAvailabilityTest extends TestCase
{
	public function test_format_reason_rejects_empty_short_long_and_bad_chars(): void
	{
		$this->assertSame(RegisterUsernameAvailability::REASON_INVALID, RegisterUsernameAvailability::formatReason(''));
		$this->assertSame(RegisterUsernameAvailability::REASON_INVALID, RegisterUsernameAvailability::formatReason('ab'));
		$this->assertSame(RegisterUsernameAvailability::REASON_INVALID, RegisterUsernameAvailability::formatReason(str_repeat('n', 26)));
		$this->assertSame(RegisterUsernameAvailability::REASON_INVALID, RegisterUsernameAvailability::formatReason('bad@name'));
		$this->assertNull(RegisterUsernameAvailability::formatReason('Nova'));
	}

	public function test_format_reason_hive_signup_requires_hive_account_rules(): void
	{
		$this->assertSame(
			RegisterUsernameAvailability::REASON_INVALID,
			RegisterUsernameAvailability::formatReason('Nova Player', true)
		);
		$this->assertNull(RegisterUsernameAvailability::formatReason('novaplayer', true));
	}

	public function test_similar_candidates_include_digits_prefix_suffix_and_stay_valid(): void
	{
		$names = RegisterUsernameAvailability::similarNameCandidates('Name');

		$this->assertContains('Name2', $names);
		$this->assertContains('Name42', $names);
		$this->assertContains('xName', $names);
		$this->assertContains('NameMoon', $names);
		$this->assertContains('NameNova', $names);
		$this->assertContains('NameHQ', $names);
		$this->assertContains('Name_1', $names);
		$this->assertContains('N4m3', $names);
		$this->assertNotContains('Name', $names);
		$this->assertSame($names, array_values(array_unique($names)));

		foreach ($names as $name) {
			$this->assertNull(RegisterUsernameAvailability::formatReason($name), $name.' should be valid');
			$this->assertLessThanOrEqual(RegisterUsernameAvailability::MAX_LENGTH, RegisterUsernameAvailability::nameLength($name));
		}
	}

	public function test_similar_candidates_strip_invalid_characters_and_respect_max_length(): void
	{
		$fromJunk = RegisterUsernameAvailability::similarNameCandidates('Name!');
		$this->assertContains('Name2', $fromJunk);

		$long = str_repeat('n', 24);
		foreach (RegisterUsernameAvailability::similarNameCandidates($long) as $name) {
			$this->assertLessThanOrEqual(RegisterUsernameAvailability::MAX_LENGTH, RegisterUsernameAvailability::nameLength($name));
		}
	}

	public function test_available_email_username_has_no_suggestions(): void
	{
		$service = $this->service([], []);
		$result = $service->check('FreshName', 1, false);

		$this->assertTrue($result['available']);
		$this->assertNull($result['reason']);
		$this->assertSame([], $result['suggestions']);
		$this->assertFalse($result['hiveOwn']);
	}

	public function test_taken_in_game_returns_available_alternatives(): void
	{
		$service = $this->service(['Name', 'Name2'], []);
		$result = $service->check('Name', 1, false);

		$this->assertFalse($result['available']);
		$this->assertSame(RegisterUsernameAvailability::REASON_TAKEN_GAME, $result['reason']);
		$this->assertNotContains('Name', $result['suggestions']);
		$this->assertNotContains('Name2', $result['suggestions']);
		$this->assertContains('Name42', $result['suggestions']);
		$this->assertGreaterThanOrEqual(3, count($result['suggestions']));
		$this->assertLessThanOrEqual(5, count($result['suggestions']));
	}

	public function test_taken_on_hive_blocks_email_signup_and_skips_hive_collisions(): void
	{
		$hiveCalls = [];
		$service = new RegisterUsernameAvailability(
			static fn (): array => [],
			function (array $names) use (&$hiveCalls): array {
				$hiveCalls[] = $names;
				$exists = [];
				foreach ($names as $name) {
					$exists[$name] = in_array($name, ['name', 'namemoon'], true);
				}
				return $exists;
			}
		);

		$result = $service->check('Name', 1, false);

		$this->assertFalse($result['available']);
		$this->assertSame(RegisterUsernameAvailability::REASON_TAKEN_HIVE, $result['reason']);
		$this->assertNotContains('NameMoon', $result['suggestions']);
		$this->assertNotContains('Name', $result['suggestions']);
		$this->assertContains('Name2', $result['suggestions']);
		$this->assertCount(1, $hiveCalls);
		$this->assertLessThanOrEqual(RegisterUsernameAvailability::HIVE_LOOKUP_CAP, count($hiveCalls[0]));
		$this->assertSame('name', $hiveCalls[0][0]);
	}

	public function test_hive_signup_treats_matching_hive_account_as_available(): void
	{
		$service = $this->service([], ['alice' => true]);
		$result = $service->check('Alice', 3, true);

		$this->assertTrue($result['available']);
		$this->assertTrue($result['hiveOwn']);
		$this->assertNull($result['reason']);
		$this->assertSame([], $result['suggestions']);
	}

	public function test_hive_signup_still_blocks_in_game_collision(): void
	{
		$service = $this->service(['alice'], ['alice' => true]);
		$result = $service->check('alice', 1, true);

		$this->assertFalse($result['available']);
		$this->assertSame(RegisterUsernameAvailability::REASON_TAKEN_GAME, $result['reason']);
		$this->assertSame([], $result['suggestions']);
	}

	public function test_hive_signup_missing_chain_account_is_unavailable(): void
	{
		$service = $this->service([], []);
		$result = $service->check('notarealhive', 1, true);

		$this->assertFalse($result['available']);
		$this->assertSame(RegisterUsernameAvailability::REASON_MISSING_HIVE, $result['reason']);
	}

	public function test_empty_username_is_invalid_without_lookups(): void
	{
		$lookedUp = false;
		$service = new RegisterUsernameAvailability(
			function () use (&$lookedUp): array {
				$lookedUp = true;
				return [];
			},
			static fn (): array => []
		);

		$result = $service->check('  ', 1, false);
		$this->assertFalse($result['available']);
		$this->assertSame(RegisterUsernameAvailability::REASON_INVALID, $result['reason']);
		$this->assertFalse($lookedUp);
	}

	public function test_lookup_game_taken_reads_users_and_pending_rows(): void
	{
		$db = $this->createStub(DatabaseInterface::class);
		$db->method('select')->willReturnCallback(function (string $sql, array $params): array {
			$this->assertStringContainsString('%%USERS%%', $sql);
			$this->assertStringContainsString('%%USERS_VALID%%', $sql);
			$this->assertSame(1, $params[':universe']);
			$this->assertSame('Alice', $params[':n0']);
			return [
				['username' => 'Alice'],
				['userName' => 'Pending'],
			];
		});

		$taken = RegisterUsernameAvailability::lookupGameTaken($db, 1, ['Alice', 'Alice', '']);
		$this->assertSame(['Alice', 'Pending'], $taken);
	}

	public function test_lookup_game_taken_skips_empty_universe_or_names(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->never())->method('select');

		$this->assertSame([], RegisterUsernameAvailability::lookupGameTaken($db, 0, ['Name']));
		$this->assertSame([], RegisterUsernameAvailability::lookupGameTaken($db, 1, ['', '  ']));
	}

	public function test_lookup_game_taken_returns_empty_when_select_fails(): void
	{
		$db = $this->createStub(DatabaseInterface::class);
		$db->method('select')->willReturn(false);

		$this->assertSame([], RegisterUsernameAvailability::lookupGameTaken($db, 1, ['Name']));
	}

	public function test_hive_lookup_non_array_is_treated_as_empty(): void
	{
		$service = new RegisterUsernameAvailability(
			static fn (): array => [],
			static fn (): mixed => null
		);

		$result = $service->check('FreshName', 1, false);
		$this->assertTrue($result['available']);
	}

	public function test_similar_hive_candidates_are_lowercase_and_hive_valid(): void
	{
		$names = RegisterUsernameAvailability::similarNameCandidates('Alice', true);
		$this->assertContains('alice2', $names);
		$this->assertContains('xalice', $names);
		foreach ($names as $name) {
			$this->assertTrue(\HiveNova\Core\HiveUtil::isAccountValid($name), $name);
		}
	}

	public function test_from_defaults_uses_injected_hive_lookup(): void
	{
		$db = $this->createStub(DatabaseInterface::class);
		$db->method('select')->willReturn([]);
		$service = RegisterUsernameAvailability::fromDefaults(
			$db,
			static fn (): array => ['freshname' => false]
		);

		$result = $service->check('FreshName', 2, false);
		$this->assertTrue($result['available']);
	}

	public function test_unverified_hive_format_suggestions_are_skipped(): void
	{
		$service = new RegisterUsernameAvailability(
			static fn (): array => ['Name'],
			static fn (): array => ['name' => true]
		);

		$result = $service->check('Name', 1, false);
		foreach ($result['suggestions'] as $suggestion) {
			$this->assertFalse(
				\HiveNova\Core\HiveUtil::isAccountValid(strtolower($suggestion)),
				$suggestion.' must not be suggested without a Hive lookup result'
			);
		}
	}

	/**
	 * @param list<string> $gameTaken
	 * @param array<string, bool> $hiveExists
	 */
	private function service(array $gameTaken, array $hiveExists): RegisterUsernameAvailability
	{
		$takenSet = array_fill_keys($gameTaken, true);
		return new RegisterUsernameAvailability(
			static function (int $universeId, array $names) use ($takenSet): array {
				$found = [];
				foreach ($names as $name) {
					if (isset($takenSet[$name])) {
						$found[] = $name;
					}
				}
				return $found;
			},
			static function (array $names) use ($hiveExists): array {
				$result = [];
				foreach ($names as $name) {
					$result[$name] = !empty($hiveExists[$name]);
				}
				return $result;
			}
		);
	}
}
