<?php

use HiveNova\Repository\MessageRepository;
use PHPUnit\Framework\TestCase;

class MessageRepositoryTest extends TestCase
{
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
}
