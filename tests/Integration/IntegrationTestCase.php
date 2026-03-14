<?php

use HiveNova\Core\Database;
use HiveNova\Core\Config;
use HiveNova\Core\PlayerUtil;
use HiveNova\Core\Universe;

use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected static array $createdUserIds = [];

    protected static function makeUniqueUsername(string $prefix): string
    {
        return $prefix . '_' . uniqid();
    }

    protected static function createTestPlayer(
        string $username,
        int $galaxy = 2,
        int $system = 1,
        int $position = 8
    ): array {
        $hash = PlayerUtil::cryptPassword('testpass123');
        [$userId, $planetId] = PlayerUtil::createPlayer(
            Universe::current(),
            $username,
            $hash,
            $username . '@test.local',
            '',
            'en',
            $galaxy,
            $system,
            $position
        );
        self::$createdUserIds[] = $userId;
        return [$userId, $planetId];
    }

    protected static function deleteTestPlayer(int $userId): void
    {
        PlayerUtil::deletePlayer($userId);
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$createdUserIds as $userId) {
            try {
                PlayerUtil::deletePlayer($userId);
            } catch (Throwable) {
                // already deleted or root user — ignore
            }
        }
        self::$createdUserIds = [];
    }
}
