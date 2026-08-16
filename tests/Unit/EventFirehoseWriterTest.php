<?php

declare(strict_types=1);

use HiveNova\Core\EventFirehoseWriter;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class EventFirehoseWriterTest extends TestCase
{
	use SwapDatabaseInstance;

	private FakeDatabase $fake;

	protected function setUp(): void
	{
		$this->fake = new FakeDatabase();
		$this->swapDatabaseInstance($this->fake);
	}

	protected function tearDown(): void
	{
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function test_size_bucket_boundaries(): void
	{
		$this->assertSame(EventFirehoseWriter::SIZE_SMALL, EventFirehoseWriter::sizeBucket(0));
		$this->assertSame(EventFirehoseWriter::SIZE_SMALL, EventFirehoseWriter::sizeBucket(999_999));
		$this->assertSame(EventFirehoseWriter::SIZE_MEDIUM, EventFirehoseWriter::sizeBucket(1_000_000));
		$this->assertSame(EventFirehoseWriter::SIZE_MEDIUM, EventFirehoseWriter::sizeBucket(99_999_999));
		$this->assertSame(EventFirehoseWriter::SIZE_LARGE, EventFirehoseWriter::sizeBucket(100_000_000));
	}

	public function test_record_stores_enums_not_raw_units_or_rid(): void
	{
		EventFirehoseWriter::record(1, 1_700_000_000, 1_500_000, 'a');

		$rows = $this->fake->achievement->universeEvents;
		$this->assertCount(1, $rows);
		$row = $rows[0];
		$this->assertSame('battle', $row['event_type']);
		$this->assertSame('medium', $row['size_bucket']);
		$this->assertSame('attacker', $row['outcome']);
		$this->assertArrayNotHasKey('rid', $row);
		$this->assertArrayNotHasKey('units', $row);
		$this->assertArrayNotHasKey(':reportId', $row);
	}

	public function test_record_failure_does_not_throw(): void
	{
		$this->fake->achievement->throwOnUniverseEventsInsert = true;
		EventFirehoseWriter::record(1, 1, 10, 'w');
		$this->assertSame([], $this->fake->achievement->universeEvents);
	}
}
