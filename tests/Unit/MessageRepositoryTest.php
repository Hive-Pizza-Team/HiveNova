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

    public function testLostFilterClauseMatchesCombatUnitLosses(): void
    {
        $clause = MessageRepository::lostFilterClause(3, 'lost');
        $this->assertStringContainsString('message_text REGEXP :lostNeedle', $clause['sql']);
        $this->assertSame(MessageRepository::COMBAT_UNIT_LOSS_REGEXP, $clause['params'][':lostNeedle']);
    }

    public function testLostFilterClauseMatchesExpeditionUnitLosses(): void
    {
        $clause = MessageRepository::lostFilterClause(15, 'lost');
        $this->assertSame(MessageRepository::COMBAT_UNIT_LOSS_REGEXP, $clause['params'][':lostNeedle']);
    }

    public function testLostFilterClauseMatchesSpyLostMarker(): void
    {
        $clause = MessageRepository::lostFilterClause(0, 'lost');
        $this->assertSame(MessageRepository::LOST_SPY_LIKE, $clause['params'][':lostNeedle']);
    }

    public function testLostFilterClauseMatchesAllInboxLostReports(): void
    {
        $clause = MessageRepository::lostFilterClause(100, 'lost');
        $this->assertStringContainsString('message_text REGEXP :lostNeedle', $clause['sql']);
        $this->assertStringContainsString('message_text LIKE :lostNeedleSpy', $clause['sql']);
        $this->assertSame(MessageRepository::COMBAT_UNIT_LOSS_REGEXP, $clause['params'][':lostNeedle']);
        $this->assertSame(MessageRepository::LOST_SPY_LIKE, $clause['params'][':lostNeedleSpy']);
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
        $this->assertStringContainsString('REGEXP :lostNeedle', $this->lastSelectSingle['sql']);
        $this->assertSame(MessageRepository::COMBAT_UNIT_LOSS_REGEXP, $this->lastSelectSingle['params'][':lostNeedle']);
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

    public function testCountMessagesAllInboxAppliesLostFilter(): void
    {
        $this->stubDatabase();
        MessageRepository::countMessages(9, 100, false, 'lost');
        $this->assertSame(MessageRepository::COMBAT_UNIT_LOSS_REGEXP, $this->lastSelectSingle['params'][':lostNeedle']);
        $this->assertSame(MessageRepository::LOST_SPY_LIKE, $this->lastSelectSingle['params'][':lostNeedleSpy']);
    }

    public function testGetMessagesPagedAppliesLostFilterForSpy(): void
    {
        $this->stubDatabase();
        $rows = MessageRepository::getMessagesPaged(9, 0, 0, 10, 'lost');
        $this->assertSame([['message_id' => 1]], $rows);
        $this->assertStringContainsString('LIKE :lostNeedle', $this->lastSelect['sql']);
        $this->assertSame(MessageRepository::LOST_SPY_LIKE, $this->lastSelect['params'][':lostNeedle']);
        $this->assertSame(0, $this->lastSelect['params'][':offset']);
        $this->assertSame(10, $this->lastSelect['params'][':limit']);
    }

    public function testGetMessagesPagedAllCategoryAppliesLostFilter(): void
    {
        $this->stubDatabase();
        MessageRepository::getMessagesPaged(9, 100, 5, 10, 'lost');
        $this->assertSame(MessageRepository::COMBAT_UNIT_LOSS_REGEXP, $this->lastSelect['params'][':lostNeedle']);
        $this->assertSame(MessageRepository::LOST_SPY_LIKE, $this->lastSelect['params'][':lostNeedleSpy']);
        $this->assertSame(5, $this->lastSelect['params'][':offset']);
    }

    public function testGetMessagesPagedOutboxIgnoresLostFilter(): void
    {
        $this->stubDatabase();
        MessageRepository::getMessagesPaged(9, 999, 0, 10, 'lost');
        $this->assertArrayNotHasKey(':lostNeedle', $this->lastSelect['params']);
    }

    public function testCombatUnitLossMatchesWinWithCasualties(): void
    {
        $win = $this->combatReportHtml('raportWin', 'raportLose', 'game.php?page=raport&raport=abc', '10', '20');
        $this->assertStringContainsString('raportLose', $win);
        $this->assertTrue(MessageRepository::combatMessageHasUnitLosses($win));
    }

    public function testCombatUnitLossMatchesDefenderFleetOrDefenseLossesOnWin(): void
    {
        $win = $this->combatReportHtml('raportWin', 'raportLose', 'game.php?page=raport&raport=abc', '0', '5.000');
        $this->assertTrue(MessageRepository::combatMessageHasUnitLosses($win));
    }

    public function testCombatUnitLossMatchesOwnFleetLossesWhenEnemyLostNothing(): void
    {
        $win = $this->combatReportHtml('raportWin', 'raportLose', 'game.php?page=raport&raport=abc', '1.200', '0');
        $this->assertTrue(MessageRepository::combatMessageHasUnitLosses($win));
    }

    public function testCombatUnitLossSkipsResourceOnlyRaidWithNoUnitLosses(): void
    {
        $raid = $this->combatReportHtml('raportLose', 'raportWin', 'game.php?page=raport&raport=abc', '0', '0', '1.000', '500');
        $this->assertStringContainsString('raportLose', $raid);
        $this->assertStringContainsString('reportSteal', $raid);
        $this->assertFalse(MessageRepository::combatMessageHasUnitLosses($raid));
    }

    public function testCombatUnitLossMatchesDefeatWithDefenseLosses(): void
    {
        $loss = $this->combatReportHtml('raportLose', 'raportWin', 'game.php?page=raport&raport=abc', '0', '800');
        $this->assertTrue(MessageRepository::combatMessageHasUnitLosses($loss));
    }

    public function testCombatUnitLossMatchesExpeditionCombatReportLink(): void
    {
        $loss = $this->combatReportHtml('raportLose', 'raportWin', 'CombatReport.php?raport=xyz', '4.000', '0', '0', '0', true);
        $this->assertTrue(MessageRepository::combatMessageHasUnitLosses($loss));
    }

    public function testCombatUnitLossMatchesDrawWithCasualties(): void
    {
        $draw = $this->combatReportHtml('raportDraw', 'raportDraw', 'game.php?page=raport&raport=abc', '100', '100');
        $this->assertTrue(MessageRepository::combatMessageHasUnitLosses($draw));
    }

    public function testCombatUnitLossSkipsDrawWithNoUnitLosses(): void
    {
        $draw = $this->combatReportHtml('raportDraw', 'raportDraw', 'game.php?page=raport&raport=abc', '0', '0');
        $this->assertFalse(MessageRepository::combatMessageHasUnitLosses($draw));
    }

    public function testCombatUnitLossMatchesLocalizedNumberSpan(): void
    {
        $html = $this->combatReportHtml(
            'raportWin',
            'raportLose',
            'game.php?page=raport&raport=abc',
            "<span class='ln' data-n='1200'>1.200</span>",
            "<span class='ln' data-n='0'>0</span>"
        );
        $this->assertTrue(MessageRepository::combatMessageHasUnitLosses($html));
    }

    public function testCombatUnitLossSkipsLocalizedZeroZeroRaid(): void
    {
        $html = $this->combatReportHtml(
            'raportLose',
            'raportWin',
            'game.php?page=raport&raport=abc',
            "<span class='ln' data-n='0'>0</span>",
            "<span class='ln' data-n='0'>0</span>",
            "<span class='ln' data-n='1000'>1.000</span>",
            "<span class='ln' data-n='0'>0</span>"
        );
        $this->assertFalse(MessageRepository::combatMessageHasUnitLosses($html));
    }

    public function testLostSpyNeedleMatchesDestroyedProbesOnly(): void
    {
        $lost = '<div class="spyRaportFooter"><span class="spyReportLost">destroyed</span></div>';
        $ok   = '<div class="spyRaportFooter">Chance of counter-espionage: 30%</div>';
        $this->assertTrue($this->matchesLostLike($lost, MessageRepository::LOST_SPY_LIKE));
        $this->assertFalse($this->matchesLostLike($ok, MessageRepository::LOST_SPY_LIKE));
    }

    /**
     * Minimized combat-mail HTML used by attack, destruction, and expedition.
     * Title class is the player's result; the two unit-loss spans always carry
     * attacker/defender result classes, so both raportWin and raportLose appear
     * in a winning report. Steal/debris rows use reportSteal (raportSteal on
     * expedition) so resource numbers must not trip the unit-loss filter.
     */
    private function combatReportHtml(
        string $titleClass,
        string $opponentClass,
        string $href,
        string $attackerLost = '10',
        string $defenderLost = '20',
        string $stealMetal = '0',
        string $debrisMetal = '0',
        bool $expedition = false
    ): string {
        $stealClass  = $expedition ? 'raportSteal' : 'reportSteal';
        $debrisClass = $expedition ? 'raportDebris' : 'reportDebris';
        $html = <<<HTML
<div class="raportMessage">
	<table>
		<tr>
			<td colspan="2"><a href="{$href}" target="_blank"><span class="{$titleClass}">Combat Report [1:2:3] (P)</span></a></td>
		</tr>
		<tr>
			<td>Losses</td><td><span class="{$titleClass}">Attacker: {$attackerLost}</span>&nbsp;<span class="{$opponentClass}">Defender: {$defenderLost}</span></td>
		</tr>
		<tr>
			<td>Gain</td><td><span>Metal:&nbsp;<span class="{$stealClass} element901">{$stealMetal}</span></span></td>
		</tr>
		<tr>
			<td>Debris</td><td><span>Metal:&nbsp;<span class="{$debrisClass} element901">{$debrisMetal}</span></span></td>
		</tr>
	</table>
</div>
HTML;
        return str_replace(["\n", "\t", "\r"], '', $html);
    }

    private function matchesLostLike(string $text, string $like): bool
    {
        $this->assertTrue(str_starts_with($like, '%') && str_ends_with($like, '%'));
        return str_contains($text, substr($like, 1, -1));
    }
}
