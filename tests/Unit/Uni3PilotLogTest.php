<?php

use HiveNova\Core\Uni3PilotLog;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/RecordingDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class Uni3PilotLogTest extends TestCase
{
	use SwapDatabaseInstance;

	protected function tearDown(): void
	{
		Uni3PilotLog::setRecorder(null);
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testRecorderAndDatabaseInsert(): void
	{
		$seen = [];
		Uni3PilotLog::setRecorder(function (string $event, int $universe, int $seasonId, int $userId, string $detail) use (&$seen): void {
			$seen = [$event, $universe, $seasonId, $userId, $detail];
		});
		Uni3PilotLog::record(Uni3PilotLog::ENTRY_PAID, 3, 2, 9, 'trx1');
		$this->assertSame([Uni3PilotLog::ENTRY_PAID, 3, 2, 9, 'trx1'], $seen);

		Uni3PilotLog::setRecorder(null);
		$db = new RecordingDatabase();
		$this->swapDatabaseInstance($db);
		Uni3PilotLog::record(Uni3PilotLog::REGISTER_UNI3_EMAIL, 3, 2, 9, '');
		$this->assertStringContainsString('%%UNI3_PILOT_EVENTS%%', $db->inserts[0][0]);
		$this->assertSame(Uni3PilotLog::REGISTER_UNI3_EMAIL, $db->inserts[0][1][':event']);
	}
}
