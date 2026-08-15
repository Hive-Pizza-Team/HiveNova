<?php

use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Repository\MessageRepository;
use PHPUnit\Framework\TestCase;

class MessageRepositoryTest extends TestCase
{
    private array $lastSelect = [];
    private array $lastSelectSingle = [];

    protected function tearDown(): void
    {
        $dbRef = new ReflectionProperty(Database::class, 'instance');
        $dbRef->setAccessible(true);
        $dbRef->setValue(null, null);
    }

    private function stubDatabase(): void
    {
        $db = $this->createStub(DatabaseInterface::class);
        $db->method('selectSingle')->willReturnCallback(
            function ($qry, array $params = [], $field = false) {
                $this->lastSelectSingle = ['sql' => $qry, 'params' => $params];
                if ($field === false) {
                    return ['c' => 4];
                }
                return 4;
            }
        );
        $db->method('select')->willReturnCallback(
            function ($qry, array $params = []) {
                $this->lastSelect = ['sql' => $qry, 'params' => $params];
                return [['message_id' => 1]];
            }
        );
        Database::setInstance($db);
    }

    public function testLostFilterClauseIsEmptyWhenFilterIsOff(): void
    {
        $clause = MessageRepository::lostFilterClause(3, '');
        $this->assertSame('', $clause['sql']);
        $this->assertSame([], $clause['params']);
    }

    public function testLostFilterClauseMatchesCombatRaportLose(): void
    {
        $clause = MessageRepository::lostFilterClause(3, 'lost');
        $this->assertStringContainsString('message_text LIKE :lostNeedle', $clause['sql']);
        $this->assertSame('%raportLose%', $clause['params'][':lostNeedle']);
    }

    public function testLostFilterClauseMatchesExpeditionRaportLose(): void
    {
        $clause = MessageRepository::lostFilterClause(15, 'lost');
        $this->assertSame('%raportLose%', $clause['params'][':lostNeedle']);
    }

    public function testLostFilterClauseMatchesSpyLostMarker(): void
    {
        $clause = MessageRepository::lostFilterClause(0, 'lost');
        $this->assertSame('%spyReportLost%', $clause['params'][':lostNeedle']);
    }

    public function testLostFilterClauseIgnoredForOtherCategories(): void
    {
        $clause = MessageRepository::lostFilterClause(1, 'lost');
        $this->assertSame('', $clause['sql']);
        $this->assertSame([], $clause['params']);
    }

    public function testCountMessagesAppliesLostFilterForCombat(): void
    {
        $this->stubDatabase();
        $count = MessageRepository::countMessages(9, 3, false, 'lost');
        $this->assertSame(4, $count);
        $this->assertStringContainsString('LIKE :lostNeedle', $this->lastSelectSingle['sql']);
        $this->assertSame('%raportLose%', $this->lastSelectSingle['params'][':lostNeedle']);
        $this->assertSame(9, $this->lastSelectSingle['params'][':userId']);
        $this->assertSame(3, $this->lastSelectSingle['params'][':category']);
    }

    public function testCountMessagesSkipsLostFilterForOutbox(): void
    {
        $this->stubDatabase();
        MessageRepository::countMessages(9, 999, false, 'lost');
        $this->assertArrayNotHasKey(':lostNeedle', $this->lastSelectSingle['params']);
        $this->assertStringNotContainsString('lostNeedle', $this->lastSelectSingle['sql']);
    }

    public function testCountMessagesAllInboxDoesNotAddLostNeedle(): void
    {
        $this->stubDatabase();
        MessageRepository::countMessages(9, 100, false, 'lost');
        $this->assertArrayNotHasKey(':lostNeedle', $this->lastSelectSingle['params']);
    }

    public function testGetMessagesPagedAppliesLostFilterForSpy(): void
    {
        $this->stubDatabase();
        $rows = MessageRepository::getMessagesPaged(9, 0, 0, 10, 'lost');
        $this->assertSame([['message_id' => 1]], $rows);
        $this->assertStringContainsString('LIKE :lostNeedle', $this->lastSelect['sql']);
        $this->assertSame('%spyReportLost%', $this->lastSelect['params'][':lostNeedle']);
        $this->assertSame(0, $this->lastSelect['params'][':offset']);
        $this->assertSame(10, $this->lastSelect['params'][':limit']);
    }

    public function testGetMessagesPagedAllCategoryHasNoLostNeedle(): void
    {
        $this->stubDatabase();
        MessageRepository::getMessagesPaged(9, 100, 5, 10, 'lost');
        $this->assertArrayNotHasKey(':lostNeedle', $this->lastSelect['params']);
    }

    public function testGetMessagesPagedOutboxIgnoresLostFilter(): void
    {
        $this->stubDatabase();
        MessageRepository::getMessagesPaged(9, 999, 0, 10, 'lost');
        $this->assertArrayNotHasKey(':lostNeedle', $this->lastSelect['params']);
    }
}
