<?php

declare(strict_types=1);

use HiveNova\Core\HiveAccountCreateRequestService;
use PHPUnit\Framework\TestCase;

class HiveAccountCreateRequestServiceTest extends TestCase
{
	/** @var list<array<string, mixed>> */
	private array $rows = [];

	private int $nextId = 1;

	private function service(bool $hiveExists = false): HiveAccountCreateRequestService
	{
		return new HiveAccountCreateRequestService(
			null,
			static fn (string $name): bool => $hiveExists,
			function (int $userId, int $universeId): ?array {
				foreach (array_reverse($this->rows) as $row) {
					if ((int) $row['user_id'] === $userId
						&& (int) $row['universe'] === $universeId
						&& $row['status'] === HiveAccountCreateRequestService::STATUS_PENDING
					) {
						return $row;
					}
				}

				return null;
			},
			function (array $row): int {
				$id = $this->nextId++;
				$row['id'] = $id;
				$this->rows[] = $row;

				return $id;
			},
			function (int $requestId): ?array {
				foreach ($this->rows as $row) {
					if ((int) $row['id'] === $requestId) {
						return $row;
					}
				}

				return null;
			},
			function (int $requestId, string $status, int $adminId, string $rejectReason): bool {
				foreach ($this->rows as &$row) {
					if ((int) $row['id'] !== $requestId) {
						continue;
					}
					if ($row['status'] !== HiveAccountCreateRequestService::STATUS_PENDING) {
						return false;
					}
					$row['status'] = $status;
					$row['reject_reason'] = $rejectReason;
					$row['reviewed_by'] = $adminId;
					$row['reviewed_at'] = 123;

					return true;
				}
				unset($row);

				return false;
			},
			function (int $universeId): array {
				$out = [];
				foreach ($this->rows as $row) {
					if ((int) $row['universe'] === $universeId
						&& $row['status'] === HiveAccountCreateRequestService::STATUS_PENDING
					) {
						$out[] = $row;
					}
				}

				return $out;
			}
		);
	}

	public function testSubmitHappyPath(): void
	{
		$service = $this->service();
		$result = $service->submit(
			['id' => 9, 'email' => 'p@example.com', 'hive_account' => ''],
			1,
			'NewPlayer',
			1700000000
		);

		$this->assertTrue($result['ok']);
		$this->assertSame('newplayer', $result['desired_name']);
		$this->assertSame(1, $result['request_id']);
		$this->assertCount(1, $service->listPending(1));
	}

	public function testSubmitRejectsKeychainAccounts(): void
	{
		$service = $this->service();
		$result = $service->submit(
			['id' => 9, 'email' => 'alice@hive.blog', 'hive_account' => ''],
			1,
			'alice2',
			1700000000
		);

		$this->assertFalse($result['ok']);
		$this->assertSame(HiveAccountCreateRequestService::CODE_NOT_ELIGIBLE, $result['code']);
	}

	public function testSubmitRejectsInvalidAndExistingNames(): void
	{
		$invalid = $this->service()->submit(
			['id' => 1, 'email' => 'p@example.com', 'hive_account' => ''],
			1,
			'Bad Name',
			1
		);
		$this->assertSame(HiveAccountCreateRequestService::CODE_INVALID_NAME, $invalid['code']);

		$exists = $this->service(true)->submit(
			['id' => 1, 'email' => 'p@example.com', 'hive_account' => ''],
			1,
			'alice',
			1
		);
		$this->assertSame(HiveAccountCreateRequestService::CODE_NAME_EXISTS, $exists['code']);
	}

	public function testSubmitRejectsSecondPending(): void
	{
		$service = $this->service();
		$user = ['id' => 3, 'email' => 'p@example.com', 'hive_account' => ''];
		$this->assertTrue($service->submit($user, 1, 'firstone', 1)['ok']);
		$second = $service->submit($user, 1, 'secondone', 2);
		$this->assertSame(HiveAccountCreateRequestService::CODE_PENDING_EXISTS, $second['code']);
	}

	public function testApproveAndReject(): void
	{
		$service = $this->service();
		$submit = $service->submit(
			['id' => 4, 'email' => 'p@example.com', 'hive_account' => ''],
			2,
			'queueuser',
			10
		);
		$id = (int) $submit['request_id'];

		$approved = $service->approve($id, 99);
		$this->assertTrue($approved['ok']);
		$this->assertSame(HiveAccountCreateRequestService::STATUS_APPROVED, $approved['request']['status']);
		$this->assertSame([], $service->listPending(2));

		$service2 = $this->service();
		$id2 = (int) $service2->submit(
			['id' => 5, 'email' => 'p@example.com', 'hive_account' => ''],
			2,
			'queueuser2',
			11
		)['request_id'];
		$rejected = $service2->reject($id2, 7, 'Name reserved');
		$this->assertTrue($rejected['ok']);
		$this->assertSame(HiveAccountCreateRequestService::STATUS_REJECTED, $rejected['request']['status']);
		$this->assertSame('Name reserved', $rejected['request']['reject_reason']);

		$emptyReason = $service2->reject($id2, 7, '  ');
		$this->assertSame(HiveAccountCreateRequestService::CODE_EMPTY_REASON, $emptyReason['code']);
	}

	public function testSettingsTemplateExposesCreateRequestForm(): void
	{
		$tpl = file_get_contents(__DIR__.'/../../styles/templates/game/page.settings.default.tpl');
		$page = file_get_contents(__DIR__.'/../../includes/pages/game/ShowSettingsPage.php');
		$admin = file_get_contents(__DIR__.'/../../includes/pages/adm/ShowHiveCreateRequestsPage.php');

		$this->assertIsString($tpl);
		$this->assertIsString($page);
		$this->assertIsString($admin);
		$this->assertStringContainsString('hiveCreateAvailable', $tpl);
		$this->assertStringContainsString('requestHiveCreate', $page);
		$this->assertStringContainsString('HiveAccountCreateRequestService', $admin);
	}
}
