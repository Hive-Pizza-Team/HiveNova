<?php

declare(strict_types=1);

use HiveNova\Core\ArraySeasonSbtGrantStore;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\DatabaseSeasonGrantBadgeSource;
use HiveNova\Core\DatabaseSeasonSbtGrantStore;
use HiveNova\Core\NftTrackerClient;
use HiveNova\Core\SeasonSbtGrantStore;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class DatabaseSeasonSbtGrantStoreTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		DatabaseSeasonSbtGrantStore::resetSchemaReadyCache();
	}

	protected function tearDown(): void
	{
		DatabaseSeasonSbtGrantStore::resetSchemaReadyCache();
		NftTrackerClient::setFetcher(null);
		parent::tearDown();
	}

	public function testSchemaReadySoftFailsAndCaches(): void
	{
		$old = new SeasonSbtScriptedDb();
		$old->dbVersion = 10;
		$this->assertFalse(DatabaseSeasonSbtGrantStore::isSchemaReady($old));
		$this->assertFalse(DatabaseSeasonSbtGrantStore::isSchemaReady($old));

		DatabaseSeasonSbtGrantStore::resetSchemaReadyCache();
		$missing = new SeasonSbtScriptedDb();
		$missing->tables = [];
		$this->assertFalse(DatabaseSeasonSbtGrantStore::isSchemaReady($missing));

		DatabaseSeasonSbtGrantStore::resetSchemaReadyCache();
		$boom = new SeasonSbtScriptedDb();
		$boom->throwSchema = true;
		$this->assertFalse(DatabaseSeasonSbtGrantStore::isSchemaReady($boom));
	}

	public function testListExistsAndInsertMapRows(): void
	{
		$db = new SeasonSbtScriptedDb();
		$store = new DatabaseSeasonSbtGrantStore($db);
		$grant = $this->grant('s1', SeasonSbtGrantStore::TIER_PARTICIPANT, 1);
		$db->rows = [
			'not-a-row',
			['season_id' => '', 'hive_user' => 'alice', 'tier' => 'nope'],
			$grant,
		];

		$this->assertSame([], $store->listForUser(0));
		$this->assertSame([], $store->listForHive(''));
		$listed = $store->listForUser(4);
		$this->assertCount(1, $listed);
		$this->assertSame('alice', $listed[0]['hive_user']);
		$this->assertSame(9, $listed[0]['id']);
		$this->assertCount(1, $store->listForHive('Alice'));

		$db->throwSelect = true;
		$this->assertSame([], $store->listForUser(4));
		$this->assertSame([], $store->listForHive('alice'));
		$this->assertFalse($store->exists('s1', 'alice', SeasonSbtGrantStore::TIER_PARTICIPANT));
		$db->throwSelect = false;

		$db->id = false;
		$this->assertFalse($store->exists('s1', 'alice', SeasonSbtGrantStore::TIER_PARTICIPANT));
		$db->id = 9;
		$this->assertTrue($store->exists('s1', 'alice', SeasonSbtGrantStore::TIER_PARTICIPANT));

		$this->assertFalse($store->insert(['season_id' => '', 'hive_user' => 'alice', 'tier' => 'participant']));
		$this->assertFalse($store->insert($grant));

		$db->id = false;
		$this->assertTrue($store->insert($grant));
		$this->assertNotEmpty($db->inserted);

		$db->throwInsert = true;
		$this->assertFalse($store->insert($grant));

		DatabaseSeasonSbtGrantStore::resetSchemaReadyCache();
		$unready = new SeasonSbtScriptedDb();
		$unready->dbVersion = 1;
		$blocked = new DatabaseSeasonSbtGrantStore($unready);
		$this->assertSame([], $blocked->listForUser(4));
		$this->assertFalse($blocked->insert($grant));
	}

	public function testArrayStoreSkipsInvalidRowsAndSorts(): void
	{
		$alpha = $this->grant('s1', SeasonSbtGrantStore::TIER_FEAT, 2);
		$alpha['feat_id'] = 'alpha';
		$beta = $this->grant('s1', SeasonSbtGrantStore::TIER_FEAT, 1);
		$beta['feat_id'] = 'beta';
		$store = new ArraySeasonSbtGrantStore([
			'not-an-array',
			$this->grant('s2', SeasonSbtGrantStore::TIER_RANK, 2),
			$this->grant('s1', SeasonSbtGrantStore::TIER_PARTICIPANT, null),
			$alpha,
			$beta,
		]);

		$this->assertSame([], $store->listForUser(0));
		$this->assertSame([], $store->listForHive(''));
		$this->assertFalse($store->insert(['season_id' => 's1', 'hive_user' => '!!!', 'tier' => 'participant']));
		$this->assertFalse($store->insert($this->grant('s1', SeasonSbtGrantStore::TIER_PARTICIPANT, null)));

		$seasons = array_column($store->all(), 'season_id');
		$this->assertSame(['s1', 's1', 's1', 's2'], $seasons);
		$this->assertSame('alice', $store->listForHive('ALICE')[0]['hive_user']);
	}

	public function testNftTrackerFetcherBranches(): void
	{
		$client = new NftTrackerClient('https://tracker.example');
		$this->assertSame([], $client->nftsForHolder('', 'ABC', 'alice'));
		$this->assertSame([], $client->nftsForHolder('!!!', 'ABC', 'alice'));
		$this->assertSame([], $client->nftsForHolder('sable', '!!!', 'alice'));

		NftTrackerClient::setFetcher(static fn (string $url): string => 'not-json');
		$this->assertSame([], $client->nftsForHolder('sable', 'MOON', 'alice'));

		NftTrackerClient::setFetcher(static fn (string $url): string => '{"user":"alice"}');
		$this->assertSame([], $client->nftsForHolder('sable', 'MOON', 'alice'));

		NftTrackerClient::setFetcher(static fn (string $url): string => '{"data":[{"id":1},"skip"]}');
		$rows = $client->nftsForHolder('sable', 'MOON', 'alice');
		$this->assertSame([['id' => 1]], $rows);
		$this->assertTrue($client->holderHasType('sable', 'MOON', 'alice'));

		NftTrackerClient::setFetcher(static fn (string $url): false => false);
		$this->assertSame([], $client->nftsForHolder('sable', 'MOON', 'alice'));
		$this->assertFalse($client->holderHasType('sable', 'MOON', 'alice'));
	}

	public function testBadgeSourceRejectsMissingUser(): void
	{
		$source = new DatabaseSeasonGrantBadgeSource(new ArraySeasonSbtGrantStore());
		$this->assertSame([], $source->badgesForUser(0));
	}

	/** @return array<string, mixed> */
	private function grant(string $season, string $tier, ?int $rank): array
	{
		return [
			'id' => 9,
			'universe' => 1,
			'user_id' => 4,
			'hive_user' => 'alice',
			'season_id' => $season,
			'tier' => $tier,
			'rank' => $rank,
			'nft_namespace' => 'sable',
			'nft_symbol' => 'MOON',
		];
	}
}

final class SeasonSbtScriptedDb implements DatabaseInterface
{
	public int $dbVersion = 54;

	/** @var list<mixed> */
	public array $tables = [['uni1_season_sbt_grants']];

	/** @var list<mixed> */
	public array $rows = [];

	public mixed $id = false;

	public bool $throwSchema = false;

	public bool $throwSelect = false;

	public bool $throwInsert = false;

	/** @var list<array<string, mixed>> */
	public array $inserted = [];

	public function select($qry, array $params = [])
	{
		if ($this->throwSelect) {
			throw new RuntimeException('select failed');
		}

		return $this->rows;
	}

	public function selectSingle($qry, array $params = [], $field = false)
	{
		if ($this->throwSchema || $this->throwSelect) {
			throw new RuntimeException('selectSingle failed');
		}
		if ($field === 'dbVersion') {
			return $this->dbVersion;
		}
		if ($field === 'id') {
			return $this->id;
		}

		return null;
	}

	public function insert($qry, array $params = [])
	{
		if ($this->throwInsert) {
			throw new RuntimeException('insert failed');
		}
		$this->inserted[] = $params;

		return true;
	}

	public function update($qry, array $params = [])
	{
		return true;
	}

	public function delete($qry, array $params = [])
	{
		return true;
	}

	public function replace($qry, array $params = [])
	{
		return true;
	}

	public function query($qry)
	{
		return true;
	}

	public function nativeQuery($qry)
	{
		return $this->tables;
	}

	public function lastInsertId()
	{
		return 1;
	}

	public function rowCount()
	{
		return 1;
	}

	public function getQueryCounter()
	{
		return 0;
	}

	public function quote($str)
	{
		return $str;
	}

	public function disconnect()
	{
	}

	public function getHandle(): ?\PDO
	{
		return null;
	}

	public function beginTransaction(): void
	{
	}

	public function commit(): void
	{
	}

	public function rollback(): void
	{
	}
}
