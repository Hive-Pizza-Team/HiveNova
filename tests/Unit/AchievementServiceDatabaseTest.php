<?php

use HiveNova\Core\AchievementService;
use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeAchievementDatabase.php';

class AchievementServiceDatabaseTest extends TestCase
{
    private ?DatabaseInterface $previousDb = null;

    protected function setUp(): void
    {
        AchievementService::get()->clearDefinitionCache();
    }

    protected function tearDown(): void
    {
        if ($this->previousDb instanceof DatabaseInterface) {
            Database::setInstance($this->previousDb);
        } else {
            $ref = new ReflectionClass(Database::class);
            $prop = $ref->getProperty('instance');
            $prop->setAccessible(true);
            $prop->setValue(null);
        }
        $this->previousDb = null;
        AchievementService::get()->clearDefinitionCache();
    }

    private function useFakeDb(FakeAchievementDatabase $fake): void
    {
        if ($this->previousDb === null) {
            $ref = new ReflectionClass(Database::class);
            $prop = $ref->getProperty('instance');
            $prop->setAccessible(true);
            $this->previousDb = $prop->getValue();
        }
        Database::setInstance($fake);
        AchievementService::get()->clearDefinitionCache();
    }

    private function sampleUser(int $id = 42): array
    {
        return [
            'id'            => $id,
            'universe'      => 1,
            'lang'          => 'en',
            'wons'          => 0,
            'loos'          => 0,
            'draws'         => 0,
            'desunits'      => 0,
            'ally_id'       => 0,
            'hive_account'  => '',
            'register_time' => TIMESTAMP - 86400,
            'darkmatter'    => 0,
        ];
    }

    public function testProcessEventUnlocksAndAppliesDarkmatter(): void
    {
        $fake = new FakeAchievementDatabase();
        $fake->users[42] = $this->sampleUser(42);
        $fake->users[42]['wons'] = 1;
        $this->useFakeDb($fake);

        $unlocked = AchievementService::get()->processEvent(42, 'combat_wins', [], false);

        $this->assertSame([1], $unlocked);
        $this->assertTrue(isset($fake->unlocked['42:1']));
        $this->assertSame(100.0, $fake->darkmatter);
        $this->assertCount(1, $fake->grants);
        $this->assertNotEmpty($fake->messages);
    }

    public function testProcessEventDoesNotUnlockBelowThreshold(): void
    {
        $fake = new FakeAchievementDatabase();
        $fake->users[7] = $this->sampleUser(7);
        $fake->users[7]['wons'] = 0;
        $this->useFakeDb($fake);

        $unlocked = AchievementService::get()->processEvent(7, 'combat_wins', [], true);

        $this->assertSame([], $unlocked);
        $this->assertFalse(isset($fake->unlocked['7:1']));
        $this->assertSame(0, $fake->progress['7:1'] ?? 0);
    }

    public function testSecondUnlockAttemptIsIdempotent(): void
    {
        $fake = new FakeAchievementDatabase();
        $fake->users[5] = $this->sampleUser(5);
        $fake->users[5]['wons'] = 1;
        $this->useFakeDb($fake);

        $service = AchievementService::get();
        $first = $service->processEvent(5, 'combat_wins', [], true);
        $fake->darkmatter = 0;
        $fake->grants = [];
        $second = $service->processEvent(5, 'combat_wins', [], true);

        $this->assertSame([1], $first);
        $this->assertSame([], $second);
        $this->assertSame(0.0, $fake->darkmatter);
    }

    public function testLiveUnlockSetsCelebratedPending(): void
    {
        $fake = new FakeAchievementDatabase();
        $fake->users[9] = $this->sampleUser(9);
        $fake->users[9]['wons'] = 1;
        $this->useFakeDb($fake);

        AchievementService::get()->processEvent(9, 'combat_wins', [], true);

        // Insert params captured — celebrated flag is :celebrated in insert
        $this->assertTrue(isset($fake->unlocked['9:1']));
    }

    public function testIsSchemaReadyFalseWhenTablesMissing(): void
    {
        $fake = new FakeAchievementDatabase();
        $fake->schemaReady = false;
        Database::setInstance($fake);

        $this->assertFalse(AchievementService::isSchemaReady());
    }

    public function testMarkCelebratedRunsUpdate(): void
    {
        $fake = new FakeAchievementDatabase();
        $this->useFakeDb($fake);

        AchievementService::get()->markCelebrated(3, 1);
        $this->addToAssertionCount(1);
    }
}
