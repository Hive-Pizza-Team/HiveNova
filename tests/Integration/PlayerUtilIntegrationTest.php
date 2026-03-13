<?php

class PlayerUtilIntegrationTest extends IntegrationTestCase
{
    // Use galaxy 3 to avoid colliding with the ci-install admin at 1:1:2
    private static int $testGalaxy   = 3;
    private static int $testSystem   = 1;
    private static int $testPosition = 8;

    public function testIsPositionFreeReturnsTrueForEmptyPosition(): void
    {
        $free = PlayerUtil::isPositionFree(
            Universe::current(),
            self::$testGalaxy,
            self::$testSystem,
            self::$testPosition
        );
        $this->assertTrue($free, 'Expect position 3:1:8 to be free before any player is placed there');
    }

    public function testCreatePlayerReturnsUserAndPlanetIds(): void
    {
        $username = self::makeUniqueUsername('pu_test');
        [$userId, $planetId] = self::createTestPlayer($username, self::$testGalaxy, self::$testSystem, self::$testPosition);

        $this->assertGreaterThan(0, (int) $userId);
        $this->assertGreaterThan(0, (int) $planetId);
    }

    public function testIsPositionFreeReturnsFalseForOccupiedPosition(): void
    {
        // Position is occupied by testCreatePlayerReturnsUserAndPlanetIds — but since tests
        // may run in isolation, create a fresh player here using a different slot.
        $username = self::makeUniqueUsername('pu_occ');
        self::createTestPlayer($username, self::$testGalaxy, self::$testSystem, 7);

        $free = PlayerUtil::isPositionFree(
            Universe::current(),
            self::$testGalaxy,
            self::$testSystem,
            7
        );
        $this->assertFalse($free, 'Position 3:1:7 should be occupied after creating a player there');
    }

    public function testCreatedPlayerExistsInDb(): void
    {
        $username = self::makeUniqueUsername('pu_db');
        [$userId] = self::createTestPlayer($username, self::$testGalaxy, self::$testSystem, 6);

        $db  = Database::get();
        $sql = 'SELECT id FROM %%USERS%% WHERE id = :id;';
        $row = $db->selectSingle($sql, [':id' => $userId]);

        $this->assertNotEmpty($row, 'Newly created user should exist in the DB');
        $this->assertEquals($userId, (int) $row['id']);
    }

    public function testCreatePlayerCreatesHomePlanet(): void
    {
        $username = self::makeUniqueUsername('pu_planet');
        [$userId, $planetId] = self::createTestPlayer($username, self::$testGalaxy, self::$testSystem, 5);

        $db  = Database::get();
        $sql = 'SELECT id, galaxy, `system`, planet FROM %%PLANETS%% WHERE id = :id AND id_owner = :owner;';
        $row = $db->selectSingle($sql, [':id' => $planetId, ':owner' => $userId]);

        $this->assertNotEmpty($row, 'Home planet row should exist');
        $this->assertEquals(self::$testGalaxy,   (int) $row['galaxy']);
        $this->assertEquals(self::$testSystem,    (int) $row['system']);
        $this->assertEquals(5,                   (int) $row['planet']);
    }

    public function testCreatePlayerAtOccupiedPositionThrows(): void
    {
        $username1 = self::makeUniqueUsername('pu_occ1');
        $username2 = self::makeUniqueUsername('pu_occ2');
        self::createTestPlayer($username1, self::$testGalaxy, 2, 8);

        $this->expectException(Exception::class);
        // Same position — should throw "Position is not empty"
        $hash = PlayerUtil::cryptPassword('testpass123');
        PlayerUtil::createPlayer(
            Universe::current(),
            $username2,
            $hash,
            $username2 . '@test.local',
            '',
            'en',
            self::$testGalaxy,
            2,
            8
        );
    }

    public function testSendMessageInsertsRow(): void
    {
        $username = self::makeUniqueUsername('pu_msg');
        [$userId] = self::createTestPlayer($username, self::$testGalaxy, 3, 8);

        $db  = Database::get();
        $sql = 'SELECT COUNT(*) AS cnt FROM %%MESSAGES%% WHERE message_owner = :uid;';
        $before = (int) $db->selectSingle($sql, [':uid' => $userId], 'cnt');

        PlayerUtil::sendMessage($userId, 0, 'System', 1, 'Test', 'Hello integration', TIMESTAMP, null, 1, Universe::current());

        $after = (int) $db->selectSingle($sql, [':uid' => $userId], 'cnt');
        $this->assertEquals($before + 1, $after, 'sendMessage should insert exactly one row');
    }

    public function testDeletePlayerRemovesUser(): void
    {
        $username = self::makeUniqueUsername('pu_del');
        [$userId] = self::createTestPlayer($username, self::$testGalaxy, 4, 8);

        // Remove from cleanup list since we delete manually
        self::$createdUserIds = array_filter(self::$createdUserIds, fn($id) => $id !== $userId);

        PlayerUtil::deletePlayer($userId);

        $db  = Database::get();
        $sql = 'SELECT COUNT(*) AS cnt FROM %%USERS%% WHERE id = :id;';
        $cnt = (int) $db->selectSingle($sql, [':id' => $userId], 'cnt');
        $this->assertEquals(0, $cnt, 'Deleted user should not appear in DB');
    }
}
