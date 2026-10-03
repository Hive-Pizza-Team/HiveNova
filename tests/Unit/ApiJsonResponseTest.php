<?php

use HiveNova\Core\ApiJsonResponse;
use PHPUnit\Framework\TestCase;

class ApiJsonResponseTest extends TestCase
{
	public function testSuccessEnvelope(): void
	{
		$json = ApiJsonResponse::encodeSuccess(['n' => 1], ['serverTime' => 10, 'planetId' => 2]);
		$decoded = json_decode($json, true);

		$this->assertTrue($decoded['ok']);
		$this->assertSame(1, $decoded['data']['n']);
		$this->assertSame(10, $decoded['meta']['serverTime']);
		$this->assertSame(2, $decoded['meta']['planetId']);
	}

	public function testErrorEnvelopeOmitsEmptyMessage(): void
	{
		$decoded = json_decode(ApiJsonResponse::encodeError('auth'), true);
		$this->assertFalse($decoded['ok']);
		$this->assertSame('auth', $decoded['error']);
		$this->assertArrayNotHasKey('message', $decoded);
	}

	public function testErrorEnvelopeIncludesMessage(): void
	{
		$decoded = json_decode(ApiJsonResponse::encodeError('csrf', 'nope'), true);
		$this->assertSame('nope', $decoded['message']);
	}

	public function testEncodeRejectsInf(): void
	{
		$this->expectException(JsonException::class);
		ApiJsonResponse::encodeSuccess(['n' => INF]);
	}
}
