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

    /** @var list<array<string, mixed>> */
    public array $statFleets = [];

    /** @var list<array<string, mixed>> */
    public array $statPlanets = [];

    /** @var list<array<string, mixed>> */
    public array $statUsers = [];

    /** @var list<array<string, mixed>> */
    public array $statAlliances = [];

    public function nativeQuery($qry)
    {
        $this->nativeQueries[] = $qry;

        return parent::nativeQuery($qry);
    }
}

class StatBuilderFakeDatabase extends FakeDatabase
{
    public function select($qry, array $params = [])
    {
        if (str_contains($qry, 'fleet_array') && str_contains($qry, '%%FLEETS%%')) {
            return $this->achievement->statFleets;
        }

        if (str_contains($qry, '%%USERS%%') && str_contains($qry, '%%STATPOINTS%%')) {
            return $this->achievement->statUsers;
        }

        if (str_contains($qry, '%%ALLIANCE%%') && str_contains($qry, '%%STATPOINTS%%')) {
            return $this->achievement->statAlliances;
        }

        if (str_contains($qry, 'FROM %%PLANETS%% as p') && str_contains($qry, 'destruyed = 0')) {
            return $this->achievement->statPlanets;
        }

        return parent::select($qry, $params);
    }
}

class StatBuilderTest extends TestCase
{
    use SwapDatabaseInstance;

    private StatBuilderFakeDatabase $fakeDb;

    protected function setUp(): void
    {
        $achievement = new TrackingFakeAchievementDatabase();
        $achievement->configUniverses = [['uni' => 1], ['uni' => 2]];

        $this->fakeDb = new StatBuilderFakeDatabase($achievement);
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

        $row = [
            'id'        => 0,
            'id_owner'  => 0,
            'universe'  => 1,
            'authlevel' => 0,
            'bana'      => 0,
            'username'  => '',
        ];
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

        $row = [
            'id'        => 0,
            'universe'  => 1,
            'ally_id'   => 0,
            'authlevel' => 0,
            'bana'      => '',
            'username'  => '',
        ];
        foreach (array_merge($reslist['tech'], $reslist['fleet'], $reslist['defense'], $reslist['missile']) as $elementId) {
            $column = $resource[$elementId] ?? null;
            if ($column !== null) {
                $row[$column] = 0;
            }
        }

        return array_replace($row, $overrides);
    }

    /**
     * @param array{
     *     planet?: array<string, mixed>,
     *     user?: array<string, mixed>,
     *     fleets?: list<array<string, mixed>>,
     *     alliances?: list<array<string, mixed>>,
     * } $options
     */
    private function seedMakeStatsData(array $options = []): void
    {
        $this->fakeDb->achievement->statPlanets = [
            $this->planetFixture(array_replace([
                'id'         => 1,
                'id_owner'   => 1,
                'authlevel'  => 0,
                'bana'       => 0,
                'username'   => 'player1',
            ], $options['planet'] ?? [])),
        ];
        $this->fakeDb->achievement->statUsers = [
            $this->userFixture(array_replace([
                'id'        => 1,
                'ally_id'   => 0,
                'authlevel' => 0,
                'bana'      => '',
                'username'  => 'player1',
            ], $options['user'] ?? [])),
        ];
        $this->fakeDb->achievement->statFleets = $options['fleets'] ?? [];
        $this->fakeDb->achievement->statAlliances = $options['alliances'] ?? [];
    }

    /** @param list<string> $nativeQueries */
    private function findUserStatInsert(array $nativeQueries, int $userId = 1): ?string
    {
        foreach ($nativeQueries as $query) {
            if (!str_contains($query, 'INSERT INTO  %%STATPOINTS%%')) {
                continue;
            }

            if (str_contains($query, '(' . $userId . ',')
                && preg_match('/\(' . $userId . ',\s*\d*,\s*1,\s*\d+,/', $query) === 1) {
                return $query;
            }
        }

        return null;
    }

    /** @param list<string> $nativeQueries */
    private function findAllianceStatInsert(array $nativeQueries, int $allianceId): ?string
    {
        foreach ($nativeQueries as $query) {
            if (str_contains($query, 'INSERT INTO %%STATPOINTS%%')
                && str_contains($query, '(' . $allianceId . ', 0, 2,')) {
                return $query;
            }
        }

        return null;
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

    public function testGetTechnoPointsCalculatesLevelsAndRecords(): void
    {
        $GLOBALS['pricelist'][110] = [
            'cost'   => [901 => 200, 902 => 100, 903 => 0],
            'factor' => 2,
        ];

        $builder = $this->newBuilder();
        $result = $this->invokePrivate($builder, 'GetTechnoPoints', $this->userFixture([
            'id'             => 17,
            'universe'       => 1,
            'shielding_tech' => 2,
        ]));

        $this->assertSame(2, $result['count']);
        $this->assertEqualsWithDelta(0.9, $result['points'], 0.0001);

        $records = $this->getPrivateProperty($builder, 'recordData');
        $this->assertSame([17], $records[1][110][2]);
    }

    public function testGetDefensePointsRecordsNonZeroUnits(): void
    {
        $builder = $this->newBuilder();
        $this->invokePrivate($builder, 'GetDefensePoints', $this->userFixture([
            'id'              => 19,
            'universe'        => 1,
            'rocket_launcher' => 2,
        ]));

        $records = $this->getPrivateProperty($builder, 'recordData');
        $this->assertSame([19], $records[1][401][2]);
    }

    public function testGetOfficerPointsSkipsZeroLevels(): void
    {
        global $resource;

        $GLOBALS['resource'][601] = 'officer_601';

        $builder = $this->newBuilder();
        $this->invokePrivate($builder, 'GetOfficerPoints', [
            'id'          => 21,
            'universe'    => 1,
            'officer_601' => 0,
        ]);

        $this->assertSame([], $this->getPrivateProperty($builder, 'recordData'));
    }

    public function testGetBuildPointsAccumulatesHigherLevels(): void
    {
        $builder = $this->newBuilder();
        $result = $this->invokePrivate($builder, 'GetBuildPoints', $this->planetFixture([
            'id_owner'   => 23,
            'universe'   => 1,
            'metal_mine' => 3,
        ]));

        $this->assertSame(3, $result['count']);
        $this->assertEqualsWithDelta(0.35625, $result['points'], 0.0001);
    }

    public function testGetUsersInfosFromDBAggregatesFlyingFleets(): void
    {
        $this->fakeDb->achievement->statFleets = [
            ['fleet_owner' => 5, 'fleet_array' => '202,2;202,3;'],
            ['fleet_owner' => 7, 'fleet_array' => '210,1;'],
        ];

        $builder = $this->newBuilder();
        $data = $this->invokePrivate($builder, 'GetUsersInfosFromDB');

        $this->assertEquals(5, $data['Fleets'][5][202]);
        $this->assertEquals(1, $data['Fleets'][7][210]);
        $this->assertArrayHasKey('Planets', $data);
        $this->assertArrayHasKey('Users', $data);
        $this->assertArrayHasKey('Alliance', $data);
    }

    public function testGetUsersInfosFromDBSkipsEmptyFleetSegments(): void
    {
        $this->fakeDb->achievement->statFleets = [
            ['fleet_owner' => 8, 'fleet_array' => ';202,4;'],
        ];

        $builder = $this->newBuilder();
        $data = $this->invokePrivate($builder, 'GetUsersInfosFromDB');

        $this->assertEquals(4, $data['Fleets'][8][202]);
    }

    public function testWriteRecordDataSamplesThreeWhenMoreThanThreeWinners(): void
    {
        $builder = $this->newBuilder();

        foreach ([1, 2, 3, 4, 5] as $userId) {
            $this->invokePrivate($builder, 'setRecords', $userId, 202, 10, 1);
        }

        $this->invokePrivate($builder, 'writeRecordData');

        $insert = $this->fakeDb->achievement->nativeQueries[1] ?? '';
        preg_match_all('/\(\d+,202,10,1\)/', $insert, $matches);
        $this->assertCount(3, $matches[0]);
    }

    public function testWriteRecordDataKeepsHighestAmountPerElement(): void
    {
        $builder = $this->newBuilder();
        $this->invokePrivate($builder, 'setRecords', 1, 202, 5, 1);
        $this->invokePrivate($builder, 'setRecords', 2, 202, 10, 1);
        $this->invokePrivate($builder, 'writeRecordData');

        $insert = $this->fakeDb->achievement->nativeQueries[1] ?? '';
        $this->assertStringContainsString('(2,202,10,1)', $insert);
        $this->assertStringNotContainsString(',202,5,1)', $insert);
    }

    public function testWriteRecordDataIncludesAllWinnersWhenThreeOrFewer(): void
    {
        $builder = $this->newBuilder();
        $this->invokePrivate($builder, 'setRecords', 3, 401, 7, 2);
        $this->invokePrivate($builder, 'setRecords', 4, 401, 7, 2);
        $this->invokePrivate($builder, 'writeRecordData');

        $insert = $this->fakeDb->achievement->nativeQueries[1] ?? '';
        $this->assertStringContainsString('(3,401,7,2)', $insert);
        $this->assertStringContainsString('(4,401,7,2)', $insert);
    }

    public function testSaveDataIntoDBIgnoresEmptyQueryFragments(): void
    {
        $builder = $this->newBuilder();
        $this->invokePrivate($builder, 'SaveDataIntoDB', 'SELECT 1;;');

        $this->assertCount(1, $this->fakeDb->achievement->nativeQueries);
        $this->assertSame('SELECT 1', trim($this->fakeDb->achievement->nativeQueries[0]));
    }

    public function testCheckUniverseAccountsWithCompleteUniData(): void
    {
        $builder = $this->newBuilder();
        $this->invokePrivate($builder, 'CheckUniverseAccounts', [1 => 9, 2 => 4]);

        $this->assertSame(9, Config::get(1)->users_amount);
        $this->assertSame(4, Config::get(2)->users_amount);
    }

    public function testMakeStatsWithFakedMinimalDataReturnsStatsInfo(): void
    {
        $this->seedMakeStatsData([
            'planet' => ['metal_mine' => 1],
            'user'   => ['light_fighter' => 2],
        ]);

        $builder = $this->newBuilder();
        $info = $builder->MakeStats();

        $this->assertSame(TIMESTAMP, $info['stats_time']);
        $this->assertArrayHasKey('totaltime', $info);
        $this->assertNotEmpty($this->fakeDb->achievement->nativeQueries);
        $this->assertStringContainsString(
            'TRUNCATE TABLE %%STATPOINTS%%',
            $this->fakeDb->achievement->nativeQueries[0]
        );
    }

    public function testMakeStatsMergesFlyingFleetShipsIntoUserTotals(): void
    {
        $this->seedMakeStatsData([
            'user' => ['light_fighter' => 1],
            'fleets' => [
                ['fleet_owner' => 1, 'fleet_array' => '202,2;'],
            ],
        ]);

        $builder = $this->newBuilder();
        $builder->MakeStats();

        $userInsert = $this->findUserStatInsert($this->fakeDb->achievement->nativeQueries);

        $this->assertNotNull($userInsert);
        $this->assertStringContainsString(', 0, 12, 3,', $userInsert);
    }

    public function testMakeStatsWritesAllianceRowsWhenUsersBelongToAlliance(): void
    {
        $this->seedMakeStatsData([
            'planet' => ['metal_mine' => 2],
            'user'   => [
                'ally_id'       => 5,
                'light_fighter' => 1,
            ],
            'alliances' => [[
                'id'             => 5,
                'ally_universe'  => 1,
                'old_tech_rank'  => 0,
                'old_build_rank' => 0,
                'old_defs_rank'  => 0,
                'old_fleet_rank' => 0,
                'old_total_rank' => 0,
            ]],
        ]);

        $builder = $this->newBuilder();
        $builder->MakeStats();

        $allianceInsert = $this->findAllianceStatInsert($this->fakeDb->achievement->nativeQueries, 5);

        $this->assertNotNull($allianceInsert);
        $this->assertStringContainsString('INSERT INTO %%STATPOINTS%%', $allianceInsert);
    }

    public function testMakeStatsZerosStaffWhenStatModeExcludesThem(): void
    {
        Config::setInstance(new Config([
            'uni'           => 1,
            'stat'          => 1,
            'stat_level'    => 2,
            'stat_settings' => 1000,
            'users_amount'  => 0,
        ]), 1);

        $this->seedMakeStatsData([
            'planet' => ['metal_mine' => 3, 'authlevel' => 2],
            'user'   => [
                'authlevel'     => 2,
                'light_fighter' => 5,
            ],
        ]);

        $builder = $this->newBuilder();
        $builder->MakeStats();

        $userInsert = $this->findUserStatInsert($this->fakeDb->achievement->nativeQueries);

        $this->assertNotNull($userInsert);
        $this->assertStringContainsString('(1,0,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0)', $userInsert);
    }
}
