<?php

use HiveNova\Core\Config;
use HiveNova\Core\PlayerUtil;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

/**
 * Extends FakeDatabase for createMoon coords lookup, homeworld exclusion, and alliance member counts.
 */
class PlayerUtilExtendedFakeDatabase extends FakeDatabase
{
    /** @var array<string, array<string, mixed>> */
    public array $parentPlanetsByCoords = [];

    /** @var array<int, array{ally_members: int}> */
    public array $alliances = [];

    /** @var list<int> */
    public array $userHomeworldPlanetIds = [];

    /** @var list<string> */
    public array $updateLog = [];

    public function selectSingle($qry, array $params = [], $field = false)
    {
        if (str_contains($qry, '%%PLANETS%%')
            && !str_contains($qry, 'COUNT(*)')
            && str_contains($qry, 'planet_type = :type')
            && str_contains($qry, ':position')) {
            $key = sprintf(
                '%d:%d:%d:%d',
                (int) ($params[':universe'] ?? 0),
                (int) ($params[':galaxy'] ?? 0),
                (int) ($params[':system'] ?? 0),
                (int) ($params[':position'] ?? 0),
            );
            $row = $this->parentPlanetsByCoords[$key] ?? null;
            if ($row === null) {
                return $field === false ? null : false;
            }

            return $field === false ? $row : ($row[$field] ?? false);
        }

        if (str_contains($qry, '%%PLANETS%%')
            && str_contains($qry, 'id NOT IN')
            && str_contains($qry, 'id_planet')) {
            $planetId = (int) ($params[':planetId'] ?? 0);
            if (in_array($planetId, $this->userHomeworldPlanetIds, true)) {
                return $field === false ? null : false;
            }

            return parent::selectSingle($qry, $params, $field);
        }

        if (str_contains($qry, '%%ALLIANCE%%') && str_contains($qry, 'ally_members')) {
            $allianceId = (int) ($params[':allianceId'] ?? 0);
            $alliance = $this->alliances[$allianceId] ?? null;
            if ($alliance === null) {
                return $field === 'ally_members' ? false : null;
            }

            return $field === 'ally_members'
                ? $alliance['ally_members']
                : ($field === false ? $alliance : ($alliance[$field] ?? false));
        }

        return parent::selectSingle($qry, $params, $field);
    }

    public function update($qry, array $params = [])
    {
        $this->updateLog[] = $qry;

        if (str_contains($qry, '%%PLANETS%%') && str_contains($qry, 'id_luna = :moonId')) {
            $planetId = (int) ($params[':planetId'] ?? 0);
            $moonId = (int) ($params[':moonId'] ?? 0);
            if (isset($this->planetRowsById[$planetId])) {
                $this->planetRowsById[$planetId]['id_luna'] = $moonId;
            }
        }

        if (str_contains($qry, '%%PLANETS%%') && str_contains($qry, 'id_luna = :resetId')) {
            $moonId = (int) ($params[':planetId'] ?? 0);
            foreach ($this->planetRowsById as &$planet) {
                if ((int) ($planet['id_luna'] ?? 0) === $moonId) {
                    $planet['id_luna'] = 0;
                }
            }
            unset($planet);
        }

        return parent::update($qry, $params);
    }
}

class PlayerUtilDatabaseTest extends TestCase
{
    use SwapDatabaseInstance;

    private PlayerUtilExtendedFakeDatabase $fake;

    protected function setUp(): void
    {
        if (!defined('DEFAULT_THEME')) {
            define('DEFAULT_THEME', 'hive');
        }
        if (!defined('ROOT_USER')) {
            define('ROOT_USER', 1);
        }

        $GLOBALS['LNG'] = ['type_planet_3' => 'Moon'];

        $this->fake = new PlayerUtilExtendedFakeDatabase();
        $this->swapDatabaseInstance($this->fake);

        if (!defined('ROOT_UNI')) {
            define('ROOT_UNI', 1);
        }

        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    private function makePlanetConfig(array $overrides = []): Config
    {
        return new Config(array_merge([
            'uni'                  => 1,
            'timezone'             => 'UTC',
            'darkmatter_start'     => 0,
            'metal_start'          => 500,
            'crystal_start'        => 500,
            'deuterium_start'      => 0,
            'initial_fields'       => 163,
            'planet_factor'        => 1.0,
            'max_galaxy'           => 5,
            'max_system'           => 499,
            'max_planets'          => 15,
            'LastSettedGalaxyPos'  => 1,
            'LastSettedSystemPos'  => 1,
            'LastSettedPlanetPos'  => 1,
            'users_amount'         => 0,
        ], $overrides));
    }

    public function testIsPositionFreeWhenNoPlanetAtCoords(): void
    {
        $this->fake->planetPositionCount = 0;
        $this->assertTrue(PlayerUtil::isPositionFree(1, 2, 100, 5, 1));
    }

    public function testIsPositionFreeReturnsFalseWhenOccupied(): void
    {
        $this->fake->planetPositionCount = 1;
        $this->assertFalse(PlayerUtil::isPositionFree(1, 2, 100, 5, 1));
    }

    public function testSendMessageInsertsRow(): void
    {
        $universe = defined('ROOT_UNI') ? ROOT_UNI : 1;
        PlayerUtil::sendMessage(7, 0, 'Tower', 5, 'Subject', 'Body text', TIMESTAMP, null, 1, $universe);

        $this->assertCount(1, $this->fake->achievement->messages);
        $this->assertSame(7, $this->fake->achievement->messages[0][':userId']);
        $this->assertSame('Body text', $this->fake->achievement->messages[0][':text']);
    }

    public function testDeletePlayerThrowsForRootUser(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Superuser #1 can't be deleted!");

        PlayerUtil::deletePlayer(ROOT_USER);
    }

    public function testDeletePlayerReturnsFalseWhenUserMissing(): void
    {
        $this->assertFalse(PlayerUtil::deletePlayer(9999));
    }

    public function testDeletePlayerReturnsTrueAndDeletesRelatedRows(): void
    {
        $this->fake->achievement->users[42] = [
            'id'       => 42,
            'universe' => 1,
            'ally_id'  => 0,
        ];

        $this->assertTrue(PlayerUtil::deletePlayer(42));

        $log = implode("\n", $this->fake->achievement->deleteLog);
        $this->assertStringContainsString('%%MESSAGES%%', $log);
        $this->assertStringContainsString('%%PLANETS%%', $log);
        $this->assertStringContainsString('%%USERS%%', $log);
        $this->assertStringContainsString('%%STATPOINTS%%', $log);
    }

    public function testDeletePlayerWithAllianceTriggersAllianceCleanup(): void
    {
        $this->fake->achievement->users[50] = [
            'id'       => 50,
            'universe' => 1,
            'ally_id'  => 3,
        ];

        $this->assertTrue(PlayerUtil::deletePlayer(50));

        $log = implode("\n", $this->fake->achievement->deleteLog);
        $this->assertStringContainsString('%%ALLIANCE%%', $log);
    }

    public function testCreatePlayerAutoPlacementWhenCoordsOmitted(): void
    {
        Config::setInstance($this->makePlanetConfig(), 1);
        $this->fake->planetPositionCount = 0;

        [$userId, $planetId] = PlayerUtil::createPlayer(
            1,
            'autoplace',
            'hashed',
            'auto@example.com',
            'autohive',
            'en',
        );

        $this->assertSame(100, $userId);
        $this->assertSame(200, $planetId);
        $this->assertArrayHasKey(100, $this->fake->achievement->users);
    }

    public function testCreatePlanetInsertsColonyAtFreePosition(): void
    {
        Config::setInstance($this->makePlanetConfig(), 1);
        $this->fake->planetPositionCount = 0;

        $planetId = PlayerUtil::createPlanet(2, 100, 8, 1, 77, 'Outpost', false, 0);

        $this->assertSame(200, $planetId);
        $this->assertArrayHasKey(200, $this->fake->planetRowsById);
        $this->assertSame(77, $this->fake->planetRowsById[200]['id_owner']);
        $this->assertSame('Outpost', $this->fake->planetInserts[0][':name']);
    }

    public function testCreatePlanetInsertsHomePlanetWithInitialFields(): void
    {
        Config::setInstance($this->makePlanetConfig(['initial_fields' => 163]), 1);
        $this->fake->planetPositionCount = 0;

        $planetId = PlayerUtil::createPlanet(1, 50, 5, 1, 88, 'Homeworld', true, 0);

        $this->assertSame(200, $planetId);
        $this->assertSame(88, $this->fake->planetRowsById[200]['id_owner']);
        $this->assertSame('Homeworld', $this->fake->planetInserts[0][':name']);
        $this->assertSame(163, $this->fake->planetInserts[0][':maxFields']);
    }

    public function testCreatePlanetThrowsWhenPositionInvalid(): void
    {
        Config::setInstance($this->makePlanetConfig(), 1);
        $this->fake->planetPositionCount = 0;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Try to create a planet at position: 99:1:1!');

        PlayerUtil::createPlanet(99, 1, 1, 1, 1);
    }

    public function testCreatePlanetThrowsWhenPositionOccupied(): void
    {
        Config::setInstance($this->makePlanetConfig(), 1);
        $this->fake->planetPositionCount = 1;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Position is not empty: 2:100:8!');

        PlayerUtil::createPlanet(2, 100, 8, 1, 1);
    }

    public function testDeletePlanetThrowsWhenPlanetMissing(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not found planet #404!');

        PlayerUtil::deletePlanet(404);
    }

    public function testDeletePlanetRemovesPlanetRow(): void
    {
        $this->fake->planetRowsById[300] = [
            'id'          => 300,
            'id_owner'    => 7,
            'planet_type' => 1,
            'id_luna'     => 0,
        ];

        $this->assertTrue(PlayerUtil::deletePlanet(300));

        $log = implode("\n", $this->fake->achievement->deleteLog);
        $this->assertStringContainsString('%%PLANETS%%', $log);
        $this->assertStringContainsString('id_luna', $log);
    }

    public function testDeletePlanetMoonTypeUsesMoonDeletionPath(): void
    {
        $this->fake->planetRowsById[301] = [
            'id'          => 301,
            'id_owner'    => 7,
            'planet_type' => 3,
            'id_luna'     => 0,
        ];

        $this->assertTrue(PlayerUtil::deletePlanet(301));

        $log = implode("\n", $this->fake->achievement->deleteLog);
        $this->assertStringContainsString('DELETE FROM %%PLANETS%% WHERE id = :planetId', $log);
        $this->assertStringNotContainsString('OR id_luna = :planetId', $log);
    }

    public function testDeletePlanetThrowsWhenPlanetIsPlayerHomeworld(): void
    {
        $this->fake->planetRowsById[400] = [
            'id'          => 400,
            'id_owner'    => 9,
            'planet_type' => 1,
            'id_luna'     => 0,
        ];
        $this->fake->userHomeworldPlanetIds = [400];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not found planet #400!');

        PlayerUtil::deletePlanet(400);
    }

    public function testDeletePlanetRegularTypeDeletesPlanetAndMoon(): void
    {
        $this->fake->planetRowsById[302] = [
            'id'          => 302,
            'id_owner'    => 7,
            'planet_type' => 1,
            'id_luna'     => 303,
        ];

        $this->assertTrue(PlayerUtil::deletePlanet(302));

        $log = implode("\n", $this->fake->achievement->deleteLog);
        $this->assertStringContainsString('OR id_luna = :planetId', $log);
    }

    public function testCreateMoonReturnsFalseWhenParentAlreadyHasMoon(): void
    {
        $this->seedParentPlanet(1, 2, 100, 8, [
            'id'       => 150,
            'id_luna'  => 999,
            'temp_max' => 40,
            'temp_min' => -10,
            'name'     => 'Homeworld',
        ]);

        $this->assertFalse(PlayerUtil::createMoon(1, 2, 100, 8, 77, 10));
        $this->assertCount(0, $this->fake->planetInserts);
    }

    public function testCreateMoonInsertsMoonAndLinksParent(): void
    {
        $this->seedParentPlanet(1, 2, 100, 8, [
            'id'       => 150,
            'id_luna'  => 0,
            'temp_max' => 40,
            'temp_min' => -10,
            'name'     => 'Homeworld',
        ]);

        $moonId = PlayerUtil::createMoon(1, 2, 100, 8, 77, 10, 8500, 'Luna');

        $this->assertSame(200, $moonId);
        $this->assertCount(1, $this->fake->planetInserts);
        $this->assertSame('Luna', $this->fake->planetInserts[0][':name']);
        $this->assertSame(77, $this->fake->planetInserts[0][':owner']);
        $this->assertSame(3, $this->fake->planetInserts[0][':type']);
        $this->assertSame(8500, $this->fake->planetInserts[0][':diameter']);
        $this->assertSame(200, $this->fake->planetRowsById[150]['id_luna']);
    }

    public function testCreateMoonUsesLngDefaultNameWhenMoonNameEmpty(): void
    {
        $this->seedParentPlanet(1, 3, 200, 4, [
            'id'       => 151,
            'id_luna'  => 0,
            'temp_max' => 30,
            'temp_min' => -5,
            'name'     => 'Colony',
        ]);

        PlayerUtil::createMoon(1, 3, 200, 4, 88, 5, 5000, '');

        $this->assertSame('Moon', $this->fake->planetInserts[0][':name']);
    }

    public function testDeletePlayerDecrementsAllianceMembersWhenMultipleMembers(): void
    {
        $this->fake->achievement->users[60] = [
            'id'       => 60,
            'universe' => 1,
            'ally_id'  => 5,
        ];
        $this->fake->alliances[5] = ['ally_members' => 3];

        $this->assertTrue(PlayerUtil::deletePlayer(60));

        $updates = implode("\n", $this->fake->updateLog);
        $deletes = implode("\n", $this->fake->achievement->deleteLog);
        $this->assertStringContainsString('ally_members = ally_members - 1', $updates);
        $this->assertStringNotContainsString('DELETE FROM %%ALLIANCE%%', $deletes);
    }

    public function testDeletePlayerDissolvesAllianceWhenLastMember(): void
    {
        $this->fake->achievement->users[61] = [
            'id'       => 61,
            'universe' => 1,
            'ally_id'  => 6,
        ];
        $this->fake->alliances[6] = ['ally_members' => 1];

        $this->assertTrue(PlayerUtil::deletePlayer(61));

        $deletes = implode("\n", $this->fake->achievement->deleteLog);
        $updates = implode("\n", $this->fake->updateLog);
        $this->assertStringContainsString('DELETE FROM %%ALLIANCE%%', $deletes);
        $this->assertStringContainsString('stat_type = :type AND id_owner = :allianceId', $deletes);
        $this->assertStringContainsString('id_ally = :resetId WHERE id_ally = :allianceId', $updates);
    }

    private function seedParentPlanet(
        int $universe,
        int $galaxy,
        int $system,
        int $position,
        array $row,
    ): void {
        $key = sprintf('%d:%d:%d:%d', $universe, $galaxy, $system, $position);
        $this->fake->parentPlanetsByCoords[$key] = $row;
        $planetId = (int) $row['id'];
        $this->fake->planetRowsById[$planetId] = array_merge([
            'id_owner'    => 0,
            'planet_type' => 1,
            'id_luna'     => 0,
        ], $row);
    }
}
