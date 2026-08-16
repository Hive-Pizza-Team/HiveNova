<?php

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\Universe;

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private ?DatabaseInterface $previousDb = null;

    protected function setUp(): void
    {
        // Reset singleton between tests
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $dbRef = new ReflectionProperty(Database::class, 'instance');
        $dbRef->setAccessible(true);
        $this->previousDb = $dbRef->getValue();
    }

    protected function tearDown(): void
    {
        $dbRef = new ReflectionProperty(Database::class, 'instance');
        $dbRef->setAccessible(true);
        if ($this->previousDb instanceof DatabaseInterface) {
            Database::setInstance($this->previousDb);
        } else {
            $dbRef->setValue(null, null);
        }
    }

    // -----------------------------------------------------------------------
    // __construct + __get
    // -----------------------------------------------------------------------

    public function testConstructAndGetReturnsValue(): void
    {
        $config = new Config(['game_speed' => 3, 'uni' => 1]);
        $this->assertSame(3, $config->game_speed);
        $this->assertSame(1, $config->uni);
    }

    public function testGetThrowsOnUnknownKey(): void
    {
        $config = new Config(['game_speed' => 1]);
        $this->expectException(UnexpectedValueException::class);
        $_ = $config->no_such_key;
    }

    public function testGetReturnsStringValue(): void
    {
        $config = new Config(['game_name' => 'HiveNova', 'uni' => 1]);
        $this->assertSame('HiveNova', $config->game_name);
    }

    // -----------------------------------------------------------------------
    // __set + __isset
    // -----------------------------------------------------------------------

    public function testSetUpdatesExistingKey(): void
    {
        $config = new Config(['game_speed' => 1, 'uni' => 1]);
        $config->game_speed = 5;
        $this->assertSame(5, $config->game_speed);
    }

    public function testSetThrowsOnUnknownKey(): void
    {
        $config = new Config(['game_speed' => 1]);
        $this->expectException(UnexpectedValueException::class);
        $config->no_such_key = 99;
    }

    public function testIssetReturnsTrueForExistingKey(): void
    {
        $config = new Config(['game_speed' => 1]);
        $this->assertTrue(isset($config->game_speed));
    }

    public function testIssetReturnsFalseForMissingKey(): void
    {
        $config = new Config(['game_speed' => 1]);
        $this->assertFalse(isset($config->no_such_key));
    }

    // -----------------------------------------------------------------------
    // getGlobalConfigKeys
    // -----------------------------------------------------------------------

    public function testGetGlobalConfigKeysReturnsNonEmptyArray(): void
    {
        $keys = Config::getGlobalConfigKeys();
        $this->assertIsArray($keys);
        $this->assertNotEmpty($keys);
    }

    public function testGetGlobalConfigKeysContainsKnownEntries(): void
    {
        $keys = Config::getGlobalConfigKeys();
        $this->assertContains('game_name', $keys);
        $this->assertContains('VERSION', $keys);
        $this->assertContains('stat', $keys);
        $this->assertContains('mail_active', $keys);
    }

    // -----------------------------------------------------------------------
    // setInstance + get
    // -----------------------------------------------------------------------

    public function testSetInstanceAndGetReturnsCorrectInstance(): void
    {
        $config = new Config(['fleet_speed' => 7500, 'uni' => 1]);
        Config::setInstance($config, 1);

        $retrieved = Config::get(1);
        $this->assertSame(7500, $retrieved->fleet_speed);
    }

    public function testSetInstanceWithNullUniverseDefaultsToOne(): void
    {
        $config = new Config(['game_speed' => 42, 'uni' => 1]);
        Config::setInstance($config);  // null → key 1

        $retrieved = Config::get(1);
        $this->assertSame(42, $retrieved->game_speed);
    }

    public function testGetThrowsForUnknownUniverse(): void
    {
        $config = new Config(['uni' => 1]);
        Config::setInstance($config, 1);

        $this->expectException(Exception::class);
        Config::get(999);
    }

    public function testMultipleInstancesAreIndependent(): void
    {
        Config::setInstance(new Config(['game_speed' => 1, 'uni' => 1]), 1);
        Config::setInstance(new Config(['game_speed' => 2, 'uni' => 2]), 2);

        $this->assertSame(1, Config::get(1)->game_speed);
        $this->assertSame(2, Config::get(2)->game_speed);
    }

    // -----------------------------------------------------------------------
    // save — no updateRecords path (no DB call)
    // -----------------------------------------------------------------------

    public function testSaveReturnsTrueWhenNothingToUpdate(): void
    {
        $config = new Config(['uni' => 1, 'game_speed' => 1]);
        // No __set calls → updateRecords is empty → save() short-circuits
        $this->assertTrue($config->save());
    }

    // -----------------------------------------------------------------------
    // getAll — always throws
    // -----------------------------------------------------------------------

    public function testGetAllThrowsDeprecatedException(): void
    {
        $this->expectException(Exception::class);
        Config::getAll();
    }

    // -----------------------------------------------------------------------
    // __set records change (still no DB if save not called)
    // -----------------------------------------------------------------------

    public function testSetUpdatesValueAndIsVisibleViaGet(): void
    {
        $config = new Config(['score' => 100, 'uni' => 1]);
        $config->score = 200;
        $this->assertSame(200, $config->score);
    }

    public function testMultipleSetCallsAllRecorded(): void
    {
        $config = new Config(['a' => 1, 'b' => 2, 'uni' => 1]);
        $config->a = 10;
        $config->b = 20;
        $this->assertSame(10, $config->a);
        $this->assertSame(20, $config->b);
    }

    // -----------------------------------------------------------------------
    // Universe integration: setInstance + get uses Universe::current()
    // -----------------------------------------------------------------------

    public function testGetWithNoArgUsesUniverseCurrent(): void
    {
        // MODE='INSTALL' → Universe::current() returns ROOT_UNI=1
        $config = new Config(['game_speed' => 9, 'uni' => 1]);
        Config::setInstance($config, 1);

        // Config::get() with no arg → universe=0 → Universe::current()=1
        $retrieved = Config::get();
        $this->assertSame(9, $retrieved->game_speed);
    }

    // -----------------------------------------------------------------------
    // save — DB path
    // -----------------------------------------------------------------------

    public function testSaveLocalKeyUpdatesOnlyCurrentUniverse(): void
    {
        $db = new ConfigRecordingDatabase();
        Database::setInstance($db);

        $uni1 = new Config(['uni' => 1, 'game_speed' => 1, 'game_name' => 'A']);
        $uni2 = new Config(['uni' => 2, 'game_speed' => 2, 'game_name' => 'A']);
        Config::setInstance($uni1, 1);
        Config::setInstance($uni2, 2);

        $uni1->game_speed = 5;
        $this->assertTrue($uni1->save());

        $this->assertCount(1, $db->updates);
        $this->assertStringContainsString('`game_speed`', $db->updates[0]['sql']);
        $this->assertStringContainsString('WHERE `UNI` = :universe', $db->updates[0]['sql']);
        $this->assertSame(1, $db->updates[0]['params'][':universe']);
        $this->assertSame(5, $db->updates[0]['params'][':game_speed']);
        $this->assertSame(2, $uni2->game_speed);
        $this->assertSame(0, $db->begins);
    }

    public function testSaveGlobalKeyUpdatesAllRowsAndOtherInstances(): void
    {
        $db = new ConfigRecordingDatabase();
        Database::setInstance($db);

        $uni1 = new Config(['uni' => 1, 'game_speed' => 1, 'game_name' => 'Old']);
        $uni2 = new Config(['uni' => 2, 'game_speed' => 2, 'game_name' => 'Old']);
        Config::setInstance($uni1, 1);
        Config::setInstance($uni2, 2);

        $uni1->game_name = 'HiveNova';
        $this->assertTrue($uni1->save());

        $this->assertCount(1, $db->updates);
        $this->assertStringContainsString('`game_name`', $db->updates[0]['sql']);
        $this->assertStringNotContainsString('WHERE `UNI`', $db->updates[0]['sql']);
        $this->assertArrayNotHasKey(':universe', $db->updates[0]['params']);
        $this->assertSame('HiveNova', $uni1->game_name);
        $this->assertSame('HiveNova', $uni2->game_name);
        $this->assertSame(0, $db->begins);
    }

    public function testSaveMixedKeysUsesTransactionAndTwoUpdates(): void
    {
        $db = new ConfigRecordingDatabase();
        Database::setInstance($db);

        $uni1 = new Config(['uni' => 1, 'game_speed' => 1, 'game_name' => 'Old']);
        $uni2 = new Config(['uni' => 2, 'game_speed' => 2, 'game_name' => 'Old']);
        Config::setInstance($uni1, 1);
        Config::setInstance($uni2, 2);

        $uni1->game_name = 'HiveNova';
        $uni1->game_speed = 9;
        $this->assertTrue($uni1->save());

        $this->assertSame(1, $db->begins);
        $this->assertSame(1, $db->commits);
        $this->assertSame(0, $db->rollbacks);
        $this->assertCount(2, $db->updates);
        $this->assertStringNotContainsString('WHERE `UNI`', $db->updates[0]['sql']);
        $this->assertStringContainsString('`game_name`', $db->updates[0]['sql']);
        $this->assertStringContainsString('WHERE `UNI` = :universe', $db->updates[1]['sql']);
        $this->assertStringContainsString('`game_speed`', $db->updates[1]['sql']);
        $this->assertSame('HiveNova', $uni2->game_name);
        $this->assertSame(2, $uni2->game_speed);
    }

    public function testSaveMixedKeysRollsBackWhenSecondUpdateFails(): void
    {
        $db = new ConfigRecordingDatabase();
        $db->failOnUpdate = 2;
        Database::setInstance($db);

        $uni1 = new Config(['uni' => 1, 'game_speed' => 1, 'game_name' => 'Old']);
        Config::setInstance($uni1, 1);

        $uni1->game_name = 'HiveNova';
        $uni1->game_speed = 9;

        $this->expectException(RuntimeException::class);
        try {
            $uni1->save();
        } finally {
            $this->assertSame(1, $db->begins);
            $this->assertSame(0, $db->commits);
            $this->assertSame(1, $db->rollbacks);
        }
    }

    public function testSaveWithNoGlobalSaveScopesGlobalKeyToCurrentUniverse(): void
    {
        $db = new ConfigRecordingDatabase();
        Database::setInstance($db);

        $uni1 = new Config(['uni' => 1, 'game_name' => 'Old']);
        $uni2 = new Config(['uni' => 2, 'game_name' => 'Old']);
        Config::setInstance($uni1, 1);
        Config::setInstance($uni2, 2);

        $uni1->game_name = 'LocalOnly';
        $this->assertTrue($uni1->save(['noGlobalSave' => true]));

        $this->assertCount(1, $db->updates);
        $this->assertStringContainsString('WHERE `UNI` = :universe', $db->updates[0]['sql']);
        $this->assertSame(1, $db->updates[0]['params'][':universe']);
        $this->assertSame('LocalOnly', $uni1->game_name);
        $this->assertSame('Old', $uni2->game_name);
    }

    public function testSaveGlobalKeyWithSingleUniverseDoesNotError(): void
    {
        $db = new ConfigRecordingDatabase();
        Database::setInstance($db);

        $uni1 = new Config(['uni' => 1, 'game_name' => 'Old']);
        Config::setInstance($uni1, 1);

        $uni1->game_name = 'Solo';
        $this->assertTrue($uni1->save());

        $this->assertCount(1, $db->updates);
        $this->assertStringNotContainsString('WHERE `UNI`', $db->updates[0]['sql']);
        $this->assertSame('Solo', $uni1->game_name);
    }
}

class ConfigRecordingDatabase implements DatabaseInterface
{
    /** @var list<array{sql: string, params: array<string, mixed>}> */
    public array $updates = [];

    public int $begins = 0;

    public int $commits = 0;

    public int $rollbacks = 0;

    public ?int $failOnUpdate = null;

    public function select($qry, array $params = array())
    {
        return [];
    }

    public function selectSingle($qry, array $params = array(), $field = false)
    {
        return false;
    }

    public function insert($qry, array $params = array())
    {
        return true;
    }

    public function update($qry, array $params = array())
    {
        if ($this->failOnUpdate !== null && count($this->updates) + 1 === $this->failOnUpdate) {
            throw new RuntimeException('update failed');
        }
        $this->updates[] = ['sql' => $qry, 'params' => $params];
        return true;
    }

    public function delete($qry, array $params = array())
    {
        return true;
    }

    public function replace($qry, array $params = array())
    {
        return true;
    }

    public function query($qry)
    {
        return true;
    }

    public function nativeQuery($qry)
    {
        return [];
    }

    public function lastInsertId()
    {
        return 0;
    }

    public function rowCount()
    {
        return 0;
    }

    public function getQueryCounter()
    {
        return count($this->updates);
    }

    public function quote($str)
    {
        return "'" . $str . "'";
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
        $this->begins++;
    }

    public function commit(): void
    {
        $this->commits++;
    }

    public function rollback(): void
    {
        $this->rollbacks++;
    }
}
