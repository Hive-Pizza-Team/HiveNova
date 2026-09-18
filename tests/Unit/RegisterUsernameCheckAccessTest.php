<?php

declare(strict_types=1);

use HiveNova\Core\RegisterUsernameCheckAccess;
use HiveNova\Core\RegisterUsernameCheckLimiter;
use PHPUnit\Framework\TestCase;

class RegisterUsernameCheckAccessTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->dir = sys_get_temp_dir().'/hivenova-reg-access-'.bin2hex(random_bytes(4)).'/';
	}

	protected function tearDown(): void
	{
		foreach (glob($this->dir.'*.json') ?: [] as $file) {
			@unlink($file);
		}
		@rmdir($this->dir);
		parent::tearDown();
	}

	public function test_closed_registration_skips_lookup(): void
	{
		$lookups = [];
		$gate = RegisterUsernameCheckAccess::runLookup(
			function (int $universeId) use (&$lookups) {
				$lookups[] = $universeId;
				return ['available' => true];
			},
			'10.0.0.1',
			1,
			1,
			null,
			$this->limiter(),
			static fn (int $universeId): bool => false,
			1000
		);

		$this->assertFalse($gate['allow']);
		$this->assertSame(RegisterUsernameCheckAccess::REASON_CLOSED, $gate['reason']);
		$this->assertSame(200, $gate['httpStatus']);
		$this->assertNull($gate['result']);
		$this->assertSame([], $lookups);
	}

	public function test_invalid_universe_is_rejected_without_lookup(): void
	{
		$lookups = 0;
		$gate = RegisterUsernameCheckAccess::runLookup(
			function () use (&$lookups) {
				$lookups++;
				return ['available' => true];
			},
			'10.0.0.1',
			99,
			1,
			null,
			$this->limiter(),
			static fn (int $universeId): bool => $universeId === 1,
			1000
		);

		$this->assertFalse($gate['allow']);
		$this->assertSame(99, $gate['universeId']);
		$this->assertSame(RegisterUsernameCheckAccess::REASON_CLOSED, $gate['reason']);
		$this->assertSame(0, $lookups);
	}

	public function test_missing_uni_uses_fallback_when_open(): void
	{
		$lookups = [];
		$gate = RegisterUsernameCheckAccess::runLookup(
			function (int $universeId) use (&$lookups) {
				$lookups[] = $universeId;
				return ['available' => true, 'reason' => null];
			},
			'10.0.0.1',
			0,
			3,
			null,
			$this->limiter(),
			static fn (int $universeId): bool => $universeId === 3,
			1000
		);

		$this->assertTrue($gate['allow']);
		$this->assertSame(3, $gate['universeId']);
		$this->assertSame(['available' => true, 'reason' => null], $gate['result']);
		$this->assertSame([3], $lookups);
	}

	public function test_over_limit_returns_429_and_skips_lookup(): void
	{
		$limiter = $this->limiter(1);
		$open = static fn (int $universeId): bool => $universeId === 1;

		$first = RegisterUsernameCheckAccess::runLookup(
			static fn (int $universeId): array => ['ok' => true],
			'10.0.0.1',
			1,
			1,
			null,
			$limiter,
			$open,
			1000
		);
		$this->assertTrue($first['allow']);

		$lookups = 0;
		$second = RegisterUsernameCheckAccess::runLookup(
			function () use (&$lookups) {
				$lookups++;
				return ['ok' => true];
			},
			'10.0.0.1',
			1,
			1,
			null,
			$limiter,
			$open,
			1001
		);

		$this->assertFalse($second['allow']);
		$this->assertSame(RegisterUsernameCheckAccess::REASON_RATE_LIMITED, $second['reason']);
		$this->assertSame(429, $second['httpStatus']);
		$this->assertGreaterThan(0, $second['retryAfter']);
		$this->assertNull($second['result']);
		$this->assertSame(0, $lookups);
	}

	public function test_rate_limit_is_checked_before_universe_gate(): void
	{
		$limiter = $this->limiter(1);
		RegisterUsernameCheckAccess::evaluate(
			'10.0.0.1',
			1,
			1,
			null,
			$limiter,
			static fn (): bool => true,
			1000
		);

		$openCalls = 0;
		$gate = RegisterUsernameCheckAccess::evaluate(
			'10.0.0.1',
			99,
			1,
			null,
			$limiter,
			function () use (&$openCalls): bool {
				$openCalls++;
				return true;
			},
			1001
		);

		$this->assertSame(RegisterUsernameCheckAccess::REASON_RATE_LIMITED, $gate['reason']);
		$this->assertSame(0, $openCalls);
	}

	public function test_deny_payload_is_safe_for_the_frontend(): void
	{
		$payload = RegisterUsernameCheckAccess::denyPayload(
			RegisterUsernameCheckAccess::REASON_RATE_LIMITED
		);

		$this->assertFalse($payload['ok']);
		$this->assertFalse($payload['available']);
		$this->assertSame('rate_limited', $payload['reason']);
		$this->assertSame([], $payload['suggestions']);
		$this->assertSame('', $payload['message']);
		$this->assertFalse($payload['hiveOwn']);
		$this->assertArrayNotHasKey('taken_game', $payload);
	}

	public function test_closed_deny_payload_is_not_a_universe_oracle(): void
	{
		$payload = RegisterUsernameCheckAccess::denyPayload(
			RegisterUsernameCheckAccess::REASON_CLOSED,
			'The registration is closed in this universe.!'
		);

		$this->assertFalse($payload['ok']);
		$this->assertFalse($payload['available']);
		$this->assertSame('closed', $payload['reason']);
		$this->assertSame([], $payload['suggestions']);
		$this->assertSame('The registration is closed in this universe.!', $payload['message']);
		$this->assertArrayNotHasKey('exists', $payload);
		$this->assertArrayNotHasKey('unknown', $payload);
		$encoded = json_encode($payload);
		$this->assertIsString($encoded);
		$this->assertStringNotContainsString('Unknown universe', $encoded);
		$this->assertStringNotContainsString('99999', $encoded);
	}

	/**
	 * @dataProvider checkUsernameAjaxProvider
	 * @param array<string, mixed> $request
	 */
	public function test_is_check_username_ajax(array $request, bool $expected): void
	{
		$this->assertSame($expected, RegisterUsernameCheckAccess::isCheckUsernameAjax($request));
	}

	/**
	 * @return list<array{0: array<string, mixed>, 1: bool}>
	 */
	public static function checkUsernameAjaxProvider(): array
	{
		return [
			[['page' => 'register', 'mode' => 'checkUsername', 'ajax' => '1'], true],
			[['page' => 'Register', 'mode' => 'checkUsername', 'ajax' => 1], true],
			[['page' => 'register', 'mode' => 'checkUsername', 'ajax' => 1, 'uni' => 99999], true],
			[['page' => 'register', 'mode' => 'show', 'ajax' => 1], false],
			[['page' => 'register', 'mode' => 'checkUsername', 'ajax' => 0], false],
			[['page' => 'login', 'mode' => 'checkUsername', 'ajax' => 1], false],
			[['page' => 'register', 'mode' => 'send'], false],
			[[], false],
		];
	}

	public function test_unknown_universe_config_fault_on_ajax_path_is_fail_closed(): void
	{
		$fault = new Exception('Unknown universe id: 99999');
		$query = [
			'page' => 'register',
			'mode' => 'checkUsername',
			'ajax' => 1,
			'uni' => 99999,
			'username' => 'OrbitFoxClosedTest',
		];

		$this->assertTrue(RegisterUsernameCheckAccess::shouldFailClosedForConfigFault($fault, $query));
		$this->assertFalse(RegisterUsernameCheckAccess::abortClosedIfAjaxConfigFault(
			new RuntimeException('connection refused'),
			$query
		));
		$this->assertFalse(RegisterUsernameCheckAccess::shouldFailClosedForConfigFault(
			$fault,
			['page' => 'register', 'mode' => 'show', 'uni' => 99999]
		));
		$this->assertFalse(RegisterUsernameCheckAccess::shouldFailClosedForConfigFault(
			new Exception('Unknown configuration key timezone!'),
			$query
		));
	}

	public function test_is_check_username_ajax_reads_request_superglobal(): void
	{
		$saved = $_REQUEST;
		$_REQUEST = ['page' => 'register', 'mode' => 'checkUsername', 'ajax' => 1];
		try {
			$this->assertTrue(RegisterUsernameCheckAccess::isCheckUsernameAjax());
		} finally {
			$_REQUEST = $saved;
		}
	}

	public function test_abort_closed_emits_json_for_unknown_universe_ajax(): void
	{
		$exited = false;
		ob_start();
		$aborted = RegisterUsernameCheckAccess::abortClosedIfAjaxConfigFault(
			new Exception('Unknown universe id: 99999'),
			[
				'page' => 'register',
				'mode' => 'checkUsername',
				'ajax' => 1,
				'uni' => 99999,
			],
			static function () use (&$exited): void {
				$exited = true;
			}
		);
		$stdout = (string) ob_get_clean();

		$this->assertTrue($aborted);
		$this->assertTrue($exited);
		$payload = json_decode($stdout, true);
		$this->assertIsArray($payload);
		$this->assertFalse($payload['ok']);
		$this->assertFalse($payload['available']);
		$this->assertSame(RegisterUsernameCheckAccess::REASON_CLOSED, $payload['reason']);
		$this->assertSame([], $payload['suggestions']);
		$this->assertSame('', $payload['message']);
		$this->assertFalse($payload['hiveOwn']);
		$this->assertStringNotContainsString('503', $stdout);
		$this->assertStringNotContainsString('Unknown universe', $stdout);
		$this->assertStringNotContainsString('/includes/', $stdout);
	}

	public function test_emit_closed_json_includes_message_without_internals(): void
	{
		$exited = false;
		ob_start();
		RegisterUsernameCheckAccess::emitClosedJson(
			'The registration is closed in this universe.!',
			static function () use (&$exited): void {
				$exited = true;
			}
		);
		$stdout = (string) ob_get_clean();

		$this->assertTrue($exited);
		$payload = json_decode($stdout, true);
		$this->assertIsArray($payload);
		$this->assertSame('closed', $payload['reason']);
		$this->assertSame('The registration is closed in this universe.!', $payload['message']);
		$this->assertStringNotContainsString('Config.php', $stdout);
	}

	public function test_unknown_universe_uses_real_registration_gate(): void
	{
		$lookups = 0;
		$gate = RegisterUsernameCheckAccess::runLookup(
			function () use (&$lookups) {
				$lookups++;
				return ['available' => true];
			},
			'10.0.0.1',
			99999,
			1,
			null,
			$this->limiter(),
			[\HiveNova\Core\LoginUniverseDefaults::class, 'isOpenForRegistration'],
			1000
		);

		$this->assertFalse($gate['allow']);
		$this->assertSame(RegisterUsernameCheckAccess::REASON_CLOSED, $gate['reason']);
		$this->assertSame(200, $gate['httpStatus']);
		$this->assertSame(0, $lookups);
	}

	private function limiter(int $max = 45): RegisterUsernameCheckLimiter
	{
		return new RegisterUsernameCheckLimiter($this->dir, $max, 60);
	}
}
