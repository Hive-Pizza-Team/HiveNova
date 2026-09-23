<?php

declare(strict_types=1);

use HiveNova\Mission\ExpeditionMessageBuilder;
use PHPUnit\Framework\TestCase;

class ExpeditionMessageBuilderTest extends TestCase
{
	public function testReportSubjectAppendsPlainCoordinates(): void
	{
		$subject = ExpeditionMessageBuilder::reportSubject(
			'Expedition report',
			'[%s:%s:%s]',
			[
				'fleet_end_galaxy' => 2,
				'fleet_end_system' => 45,
				'fleet_end_planet' => 16,
			]
		);

		$this->assertSame('Expedition report [2:45:16]', $subject);
	}

	public function testWithDestinationPrefixesClickableCoordinates(): void
	{
		$body = ExpeditionMessageBuilder::withDestination(
			'Scouts mapped a loose asteroid cluster.',
			[
				'fleet_end_galaxy' => 1,
				'fleet_end_system' => 8,
				'fleet_end_planet' => 16,
			],
			'Destination: %s'
		);

		$this->assertStringStartsWith('Destination: ', $body);
		$this->assertStringContainsString('[1:8:16]', $body);
		$this->assertStringContainsString('galaxy=1', $body);
		$this->assertStringContainsString('system=8', $body);
		$this->assertStringContainsString('<br>Scouts mapped a loose asteroid cluster.', $body);
	}
}
