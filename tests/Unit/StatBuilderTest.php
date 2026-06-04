<?php

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\StatBuilder;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class TrackingFakeAchievementDatabase extends FakeAchievementDatabase
{
    /** @var list<string> */
    public array $nativeQueries = [];

    public function nativeQuery($qry)
    {
        $this->nativeQueries[] = $qry;

        return parent::nativeQuery($qry);
    }
}

class StatBuilderTest extends TestCase
{
    use SwapDatabaseInstance;

    private FakeDatabase $fakeDb;

    protected function setUp(): void
    {
        $achievement = new TrackingFakeAchievementDatabase();
        $achievement->configUniverses = [['uni' => 1], ['uni' => 2]];

        $this->fakeDb = new FakeDatabase($achievement);
        $this->swapDatabaseInstance($this->fakeDb);

        Config::setInstance(new Config([
            'uni' => 1,
            'stat' => 0,
            'stat_level' => 2,
            'stat_settings' => 1000,
            'users_amount' => 0,
        ]), 1);
        Config::setInstance(new Config([
            'uni' => 2,
            'stat' => 0,
            'stat_level' => 2,
            'stat_settings' => 1000,
            'users_amount' => 0,
        ]), 2);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    private function newBuilder(): StatBuilder
    {
        return new StatBuilder();
    }

    private function invokePrivate(StatBuilder $builder, string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod(StatBuilder::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke($builder, ...$args);
    }

    private function getPrivateProperty(StatBuilder $builder, string $property): mixed
    {
        $ref = new ReflectionProperty(StatBuilder::class, $property);
        $ref->setAccessible(true);

        return $ref->getValue($builder);
    }

    /** @param array<string, mixed> $overrides */
    private function planetFixture(array $overrides = []): array
    {
        global $resource, $reslist;

        $row = ['id_owner' => 0, 'universe' => 1];
        foreach ($reslist['build'] as $buildId) {
            $column = $resource[$buildId] ?? null;
            if ($column !== null) {
                $row[$column] = 0;
            }
        }

        return array_replace($row, $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function userFixture(array $overrides = []): array
    {
        global $resource, $reslist;

        $row = ['id' => 0, 'universe' => 1];
        foreach (array_merge($reslist['tech'], $reslist['fleet'], $reslist['defense'], $reslist['missile']) as $elementId) {
            $column = $resource[$elementId] ?? null;
            if ($column !== null) {
                $row[$column] = 0;
            }
        }

        return array_replace($row, $overrides);
    }

    public function test_constructor_loads_universes_from_config(): void
    {
        $builder = $this->newBuilder();

        $this->assertSame([1, 2], $this->getPrivateProperty($builder, 'Unis'));
    }

    public function test_constructor_records_timestamp_and_memory(): void
    {
        $builder = $this->newBuilder();

        $this->assertSame(TIMESTAMP, $this->getPrivateProperty($builder, 'time'));
        $this->assertIsArray($this->getPrivateProperty($builder, 'memory'));
        $this->assertSame([], $this->getPrivateProperty($builder, 'recordData'));
    }

    public function testSomeStatsInfosReturnsExpectedKeys(): void
    {
        $builder = $this->newBuilder();
        $info = $this->invokePrivate($builder, 'SomeStatsInfos');

        $this->assertSame(TIMESTAMP, $info['stats_time']);
        $this->assertArrayHasKey('totaltime', $info);
        $this->assertArrayHasKey('memory_peak', $info);
        $this->assertSame($this->getPrivateProperty($builder, 'memory'), $info['initial_memory']);
        $this->assertArrayHasKey('end_memory', $info);
        $this->assertArrayHasKey('sql_count', $info);
    }

    public function testGetBuildPointsCalculatesLevelCostsAndRecords(): void
    {
        $builder = $this->newBuilder();
        $result = $this->invokePrivate($builder, 'GetBuildPoints', $this->planetFixture([
            'id_owner'   => 5,
            'universe'   => 1,
            'metal_mine' => 2,
        ]));

        $this->assertSame(2, $result['count']);
        $this->assertEqualsWithDelta(0.1875, $result['points'], 0.0001);

        $records = $this->getPrivateProperty($builder, 'recordData');
        $this->assertSame([5], $records[1][1][2]);
    }

    public function testGetFleetPointsCalculatesUnitsAndRecords(): void
    {
        $builder = $this->newBuilder();
        $result = $this->invokePrivate($builder, 'GetFleetPoints', $this->userFixture([
            'id'            => 9,
            'universe'      => 1,
            'light_fighter' => 3,
        ]));

        $this->assertSame(3, $result['count']);
        $this->assertEqualsWithDelta(12.0, $result['points'], 0.0001);

        $records = $this->getPrivateProperty($builder, 'recordData');
        $this->assertSame([9], $records[1][202][3]);
    }

    public function testGetDefensePointsCalculatesUnitsAndRecords(): void
    {
        $builder = $this->newBuilder();
        $result = $this->invokePrivate($builder, 'GetDefensePoints', $this->userFixture([
            'id'              => 11,
            'universe'        => 1,
            'rocket_launcher' => 4,
        ]));

        $this->assertSame(4, $result['count']);
        $this->assertEqualsWithDelta(8.0, $result['points'], 0.0001);
    }

    public function testGetTechnoPointsReturnsZeroWhenNoResearch(): void
    {
        $builder = $this->newBuilder();
        $result = $this->invokePrivate($builder, 'GetTechnoPoints', $this->userFixture([
            'id'       => 3,
            'universe' => 1,
        ]));

        $this->assertSame(0, $result['count']);
        $this->assertEquals(0, $result['points']);
    }

    public function testGetOfficerPointsRecordsNonZeroOfficers(): void
    {
        global $resource;

        $GLOBALS['resource'][601] = 'officer_601';

        $builder = $this->newBuilder();
        $user = [
            'id'       => 13,
            'universe' => 2,
            'officer_601' => 2,
        ];

        $this->invokePrivate($builder, 'GetOfficerPoints', $user);

        $records = $this->getPrivateProperty($builder, 'recordData');
        $this->assertSame([13], $records[2][601][2]);
    }

    public function testSaveDataIntoDBExecutesEachStatement(): void
    {
        $builder = $this->newBuilder();
        $this->invokePrivate($builder, 'SaveDataIntoDB', 'SELECT 1; SELECT 2;');

        $this->assertCount(2, $this->fakeDb->achievement->nativeQueries);
        $this->assertSame('SELECT 1', trim($this->fakeDb->achievement->nativeQueries[0]));
        $this->assertSame('SELECT 2', trim($this->fakeDb->achievement->nativeQueries[1]));
    }

    public function testWriteRecordDataBuildsInsertWhenRecordsExist(): void
    {
        $builder = $this->newBuilder();
        $this->invokePrivate($builder, 'setRecords', 7, 202, 5, 1);
        $this->invokePrivate($builder, 'writeRecordData');

        $this->assertCount(2, $this->fakeDb->achievement->nativeQueries);
        $this->assertStringContainsString('TRUNCATE TABLE %%RECORDS%%', $this->fakeDb->achievement->nativeQueries[0]);
        $this->assertStringContainsString('INSERT INTO %%RECORDS%%', $this->fakeDb->achievement->nativeQueries[1]);
        $this->assertStringContainsString('(7,202,5,1)', $this->fakeDb->achievement->nativeQueries[1]);
    }

    public function testWriteRecordDataSkipsInsertWhenNoRecords(): void
    {
        $builder = $this->newBuilder();
        $this->invokePrivate($builder, 'writeRecordData');

        $this->assertSame([], $this->fakeDb->achievement->nativeQueries);
    }

    public function testCheckUniverseAccountsUpdatesMissingUniverses(): void
    {
        $builder = $this->newBuilder();
        $this->invokePrivate($builder, 'CheckUniverseAccounts', [1 => 12]);

        $this->assertSame(12, Config::get(1)->users_amount);
        $this->assertSame(0, Config::get(2)->users_amount);
    }

    public function testSetNewRanksRunsWithoutError(): void
    {
        $builder = $this->newBuilder();

        $this->invokePrivate($builder, 'SetNewRanks');

        $this->assertNotEmpty($this->fakeDb->achievement->nativeQueries);
    }
}
