<?php

declare(strict_types=1);

use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\MarketPlaceService as S;
use PHPUnit\Framework\TestCase;

/**
 * Minimal DB stub for cancelOffer(): one locked row, records writes and transaction calls.
 */
final class MarketCancelDbStub implements DatabaseInterface
{
	/** @var array<string, mixed>|false */
	public $row = false;
	/** @var list<string> */
	public array $calls = [];
	/** @var list<array{string, array<string, mixed>}> */
	public array $deletes = [];
	public bool $throwOnSelect = false;

	public function select($qry, array $params = array()) { return []; }
	public function selectSingle($qry, array $params = array(), $field = false)
	{
		$this->calls[] = 'select';
		if ($this->throwOnSelect) {
			throw new RuntimeException('db down');
		}
		TestCase::assertStringContainsString('FOR UPDATE', $qry);
		TestCase::assertSame(42, $params[':fleetId']);
		return $this->row;
	}
	public function insert($qry, array $params = array()) { return true; }
	public function update($qry, array $params = array()) { return true; }
	public function delete($qry, array $params = array())
	{
		$this->deletes[] = [$qry, $params];
		return true;
	}
	public function replace($qry, array $params = array()) { return true; }
	public function query($qry) { return true; }
	public function nativeQuery($qry) { return []; }
	public function lastInsertId() { return 0; }
	public function rowCount() { return 0; }
	public function getQueryCounter() { return 0; }
	public function quote($str) { return "'" . $str . "'"; }
	public function disconnect() {}
	public function getHandle(): ?PDO { return null; }
	public function beginTransaction(): void { $this->calls[] = 'begin'; }
	public function commit(): void { $this->calls[] = 'commit'; }
	public function rollback(): void { $this->calls[] = 'rollback'; }
}

class MarketPlaceServiceTest extends TestCase
{
	private const CAP = [202 => 5000.0, 203 => 25000.0];

	/** @return array<string, mixed> */
	private function holdingOffer(array $over = []): array
	{
		return array_merge([
			'fleet_id' => 42,
			'fleet_owner' => 7,
			'fleet_mission' => FLEET_MISSION_TRADE,
			'fleet_mess' => FLEET_HOLD,
			'fleet_no_m_return' => 0,
			'buyer_fleet_id' => null,
		], $over);
	}

	public function testOwnOfferDetection(): void
	{
		$this->assertTrue(S::isOwnOffer(['fleet_owner' => '7'], 7));
		$this->assertFalse(S::isOwnOffer(['fleet_owner' => 8], 7));
		$this->assertFalse(S::isOwnOffer([], 7));
	}

	public function testResolveDiploLevelDeduplicatesAndWarWins(): void
	{
		$this->assertNull(S::resolveDiploLevel([]));
		$this->assertNull(S::resolveDiploLevel([['level' => 3, 'accept' => 0]]));
		$this->assertNull(S::resolveDiploLevel([['level' => null]]));
		$this->assertSame(3, S::resolveDiploLevel([['level' => 3, 'accept' => 1], ['level' => 3, 'accept' => 1]]));
		$this->assertSame(1, S::resolveDiploLevel([['level' => 4], ['level' => '1']]));
		$this->assertSame(5, S::resolveDiploLevel([['level' => 1, 'accept' => 1], ['level' => 5, 'accept' => 1]]));
	}

	public function testDiploBlockKey(): void
	{
		// Visible to all.
		$this->assertNull(S::diploBlockKey(S::VISIBILITY_ALL, 5, 1, 2));
		// No enemies.
		$this->assertSame('market_buyable_no_enemies', S::diploBlockKey(S::VISIBILITY_NO_ENEMIES, 5, 1, 2));
		$this->assertNull(S::diploBlockKey(S::VISIBILITY_NO_ENEMIES, null, 1, 2));
		// Partners only: same alliance or pact level <= 3.
		$this->assertNull(S::diploBlockKey(S::VISIBILITY_PARTNERS, null, 3, 3));
		$this->assertNull(S::diploBlockKey(S::VISIBILITY_PARTNERS, 3, 1, 2));
		$this->assertSame('market_buyable_only_trade_partners', S::diploBlockKey(S::VISIBILITY_PARTNERS, 4, 1, 2));
		$this->assertSame('market_buyable_only_trade_partners', S::diploBlockKey(S::VISIBILITY_PARTNERS, null, 1, 2));
		// Two players without an alliance are not "the same alliance".
		$this->assertSame('market_buyable_only_trade_partners', S::diploBlockKey(S::VISIBILITY_PARTNERS, null, 0, 0));
	}

	public function testTechBlockKey(): void
	{
		$this->assertNull(S::techBlockKey([10, 10, 10], [10, 20, 10]));
		$this->assertSame('market_buyable_no_tech', S::techBlockKey([10, 21, 10], [10, 20, 10]));
		$this->assertSame('market_buyable_no_tech', S::techBlockKey([0, 0, 11], [0, 0, 10]));
	}

	public function testPlanPickupPrefersChosenTypeThenTopsUp(): void
	{
		$plan = S::planPickup(60000, S::PAY_HEAVY_FIRST, self::CAP, [202 => 10, 203 => 10]);
		$this->assertSame([203 => 3], $plan['fleet']);
		$this->assertSame(0.0, $plan['missing']);

		$plan = S::planPickup(60000, S::PAY_LIGHT_FIRST, self::CAP, [202 => 10, 203 => 10]);
		$this->assertSame([202 => 10, 203 => 1], $plan['fleet']);
		$this->assertSame(0.0, $plan['missing']);

		$plan = S::planPickup(60000, S::PAY_HEAVY_FIRST, self::CAP, [202 => 0, 203 => 1]);
		$this->assertSame([203 => 1], $plan['fleet']);
		$this->assertSame(35000.0, $plan['missing']);

		$plan = S::planPickup(1, S::PAY_LIGHT_FIRST, self::CAP, [202 => 0, 203 => 0]);
		$this->assertSame([], $plan['fleet']);
		$this->assertSame(1.0, $plan['missing']);
	}

	public function testBuyBlockKeyOrder(): void
	{
		$res = [901 => 1000, 902 => 1000, 903 => 1000];
		$price = [901 => 0, 902 => 0, 903 => 900];
		$this->assertSame('market_your_offer', S::buyBlockKey(true, 'market_buyable_no_enemies', null, false, 5, $res, $price, 0));
		$this->assertSame('market_buyable_no_enemies', S::buyBlockKey(false, 'market_buyable_no_enemies', 'market_buyable_no_tech', true, 0, $res, $price, 0));
		$this->assertSame('market_buyable_no_tech', S::buyBlockKey(false, null, 'market_buyable_no_tech', true, 0, $res, $price, 0));
		$this->assertSame('market_reason_no_slots', S::buyBlockKey(false, null, null, false, 0, $res, $price, 0));
		$this->assertSame('market_reason_ships', S::buyBlockKey(false, null, null, true, 1, $res, $price, 0));
		// Deuterium price + fuel must both fit.
		$this->assertNull(S::buyBlockKey(false, null, null, true, 0, $res, $price, 100));
		$this->assertSame('market_reason_resources', S::buyBlockKey(false, null, null, true, 0, $res, $price, 101));
		$this->assertSame('market_reason_resources', S::buyBlockKey(false, null, null, true, 0, $res, [901 => 1001, 902 => 0, 903 => 0], 0));
		$this->assertSame('market_reason_resources', S::buyBlockKey(false, null, null, true, 0, $res, [901 => 0, 902 => 1001, 903 => 0], 0));
	}

	public function testDealRatioAndRating(): void
	{
		$ratio = [4, 2, 1];
		// 60k crystal for 100k metal at 4:2:1 -> 30000 / 25000 = 1.2
		$this->assertEqualsWithDelta(1.2, S::dealRatio([902 => 60000], 1, 100000, $ratio), 1e-9);
		$this->assertSame('good', S::dealRating(1.2));
		// 120k metal for 60k crystal -> 30000 / 30000 = 1.0
		$this->assertEqualsWithDelta(1.0, S::dealRatio([901 => 120000], 2, 60000, $ratio), 1e-9);
		$this->assertSame('fair', S::dealRating(1.0));
		$this->assertSame('bad', S::dealRating(0.94));
		$this->assertSame('none', S::dealRating(null));
		$this->assertNull(S::dealRatio([901 => 1], 9, 100, $ratio));
		$this->assertNull(S::dealRatio([901 => 1], 1, 0, $ratio));
		// A zero weight is ignored instead of dividing by zero.
		$this->assertSame(10.0, S::referenceValue([901 => 10, 902 => 99], [1, 0, 1]));
	}

	public function testCancelBlockKey(): void
	{
		$this->assertSame('market_p_msg_not_found', S::cancelBlockKey(null, 7));
		$this->assertSame('market_p_msg_not_found', S::cancelBlockKey($this->holdingOffer(), 8));
		$this->assertNull(S::cancelBlockKey($this->holdingOffer(), 7));
		$this->assertSame('market_p_msg_cannot_cancel', S::cancelBlockKey($this->holdingOffer(['buyer_fleet_id' => 99]), 7));
		$this->assertSame('market_p_msg_cannot_cancel', S::cancelBlockKey($this->holdingOffer(['fleet_mess' => FLEET_RETURN]), 7));
		$this->assertSame('market_p_msg_cannot_cancel', S::cancelBlockKey($this->holdingOffer(['fleet_mission' => 3]), 7));
		$this->assertSame('market_p_msg_cannot_cancel', S::cancelBlockKey($this->holdingOffer(['fleet_no_m_return' => 1]), 7));
	}

	public function testCancelOfferRecallsAndDeletesTradeRow(): void
	{
		$db = new MarketCancelDbStub();
		$db->row = $this->holdingOffer();
		$sent = [];
		$key = S::cancelOffer($db, 7, 42, function (array $user, int $id) use (&$sent): bool {
			$sent[] = [$user['id'], $id];
			return true;
		});
		$this->assertNull($key);
		$this->assertSame([[7, 42]], $sent);
		$this->assertSame(['begin', 'select', 'commit'], $db->calls);
		$this->assertCount(1, $db->deletes);
		$this->assertStringContainsString('buyer_fleet_id IS NULL', $db->deletes[0][0]);
		$this->assertSame([':fleetId' => 42], $db->deletes[0][1]);
	}

	public function testCancelOfferRejectsOtherPlayersAndBoughtOffers(): void
	{
		$never = function (): bool {
			$this->fail('send back must not run');
		};

		$db = new MarketCancelDbStub();
		$db->row = $this->holdingOffer();
		$this->assertSame('market_p_msg_not_found', S::cancelOffer($db, 8, 42, $never));
		$this->assertSame(['begin', 'select', 'rollback'], $db->calls);
		$this->assertSame([], $db->deletes);

		$db = new MarketCancelDbStub();
		$db->row = $this->holdingOffer(['buyer_fleet_id' => 5]);
		$this->assertSame('market_p_msg_cannot_cancel', S::cancelOffer($db, 7, 42, $never));

		$db = new MarketCancelDbStub();
		$db->row = false;
		$this->assertSame('market_p_msg_not_found', S::cancelOffer($db, 7, 42, $never));

		$db = new MarketCancelDbStub();
		$this->assertSame('market_p_msg_not_found', S::cancelOffer($db, 7, 0, $never));
		$this->assertSame([], $db->calls);
	}

	public function testCancelOfferRollsBackWhenSendBackFails(): void
	{
		$db = new MarketCancelDbStub();
		$db->row = $this->holdingOffer();
		$this->assertSame('market_p_msg_cannot_cancel', S::cancelOffer($db, 7, 42, fn(): bool => false));
		$this->assertSame(['begin', 'select', 'rollback'], $db->calls);
		$this->assertSame([], $db->deletes);
	}

	public function testCancelOfferRollsBackAndRethrowsOnError(): void
	{
		$db = new MarketCancelDbStub();
		$db->throwOnSelect = true;
		try {
			S::cancelOffer($db, 7, 42, fn(): bool => true);
			$this->fail('expected exception');
		} catch (RuntimeException $e) {
			$this->assertSame('db down', $e->getMessage());
		}
		$this->assertSame(['begin', 'select', 'rollback'], $db->calls);
	}

	public function testCancelOfferDefaultsToFleetSendBack(): void
	{
		// Default callable delegates to FleetFunctions::SendFleetBack; with no matching
		// fleet in the (swapped) database it returns false, so the cancel is rolled back.
		require_once __DIR__ . '/../Support/FakeDatabase.php';
		$ref = new ReflectionClass(\HiveNova\Core\Database::class);
		$prop = $ref->getProperty('instance');
		$prop->setAccessible(true);
		$saved = $prop->getValue();
		\HiveNova\Core\Database::setInstance(new FakeDatabase());
		try {
			$db = new MarketCancelDbStub();
			$db->row = $this->holdingOffer();
			$this->assertSame('market_p_msg_cannot_cancel', S::cancelOffer($db, 7, 42));
			$this->assertSame(['begin', 'select', 'rollback'], $db->calls);
		} finally {
			$prop->setValue(null, $saved);
		}
	}

	public function testCsrfTokenIsStableAndValidated(): void
	{
		$session = [];
		$token = S::csrfToken($session);
		$this->assertSame(32, strlen($token));
		$this->assertSame($token, S::csrfToken($session));
		$this->assertTrue(S::validCsrf($session, $token));
		$this->assertFalse(S::validCsrf($session, 'nope'));
		$this->assertFalse(S::validCsrf($session, null));
		$this->assertFalse(S::validCsrf([], $token));
		$session[S::CSRF_SESSION_KEY] = 'short';
		$this->assertNotSame('short', S::csrfToken($session));
	}

	public function testFlashIsReadOnce(): void
	{
		$session = [];
		$this->assertNull(S::pullFlash($session));
		S::pushFlash($session, 'Done', true);
		$this->assertSame(['message' => 'Done', 'ok' => true], S::pullFlash($session));
		$this->assertNull(S::pullFlash($session));
		$session[S::FLASH_SESSION_KEY] = ['message' => ''];
		$this->assertNull(S::pullFlash($session));
	}

	public function testNormalizersAndFleetSummary(): void
	{
		$this->assertSame('fleet', S::normalizeTab('fleet'));
		$this->assertSame('resources', S::normalizeTab('<script>'));
		$this->assertSame(S::PAY_LIGHT_FIRST, S::normalizePayWith('1'));
		$this->assertSame(S::PAY_HEAVY_FIRST, S::normalizePayWith(7));
		$this->assertSame('1.200 LC + 3 HC', S::fleetSummary([202 => 1200, 203 => 3, 204 => 0], [202 => 'LC', 203 => 'HC']));
		$this->assertSame('2 205', S::fleetSummary([205 => 2], []));
	}
	public function testPickupCountsFuelInTheCargoHold(): void
	{
		// QA case: 10 small cargo (5000 each) for exactly 50,000 leaves no room for 13 fuel.
		$cap = [202 => 5000.0, 203 => 25000.0];
		$fuel = static fn(array $fleet): float => 13.0 + array_sum($fleet);
		$plan = S::planPickupWithFuel(50000, S::PAY_LIGHT_FIRST, $cap, [202 => 100, 203 => 0], $fuel);
		$this->assertSame([202 => 11], $plan['fleet']);
		$this->assertSame(24.0, $plan['fuel']);
		$this->assertSame(0.0, $plan['missing']);
		$room = 11 * 5000;
		$this->assertGreaterThanOrEqual(50000 + $plan['fuel'], $room);

		// With exactly 10 ships on the planet the buy is short and must say so.
		$plan = S::planPickupWithFuel(50000, S::PAY_LIGHT_FIRST, $cap, [202 => 10, 203 => 0], $fuel);
		$this->assertSame([202 => 10], $plan['fleet']);
		$this->assertSame(23.0, $plan['missing']);
		$this->assertSame('market_reason_ships', S::buyBlockKey(false, null, null, true, $plan['missing'], [901 => 1e9, 902 => 1e9, 903 => 1e9], [901 => 50000, 902 => 0, 903 => 0], $plan['fuel']));

		// No ships: no fuel callback, everything missing.
		$plan = S::planPickupWithFuel(100, S::PAY_HEAVY_FIRST, $cap, [], function (): float {
			$this->fail('no fleet, no fuel');
		});
		$this->assertSame([], $plan['fleet']);
		$this->assertSame(0.0, $plan['fuel']);
		$this->assertSame(100.0, $plan['missing']);
	}

	public function testPickupWithFuelKeepsTypePreference(): void
	{
		$cap = [202 => 5000.0, 203 => 25000.0];
		$plan = S::planPickupWithFuel(50000, S::PAY_HEAVY_FIRST, $cap, [202 => 5, 203 => 5], static fn(): float => 10.0);
		$this->assertSame([203 => 3], $plan['fleet']);
		$this->assertSame(10.0, $plan['fuel']);
	}

	public function testDiploClassTreatsNoAllianceAsNoAlliance(): void
	{
		$this->assertSame('', S::diploClass(null, 0, 0));
		$this->assertSame('trade-ally', S::diploClass(null, 4, 4));
		$this->assertSame('trade-ally', S::diploClass(2, 4, 9));
		$this->assertSame('', S::diploClass(4, 4, 9));
		$this->assertSame('trade-enemy', S::diploClass(5, 4, 9));
	}
}
