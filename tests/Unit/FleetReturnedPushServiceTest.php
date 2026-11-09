<?php

use HiveNova\Core\FleetReturnedPushService;
use HiveNova\Core\MissionFunctions;
use HiveNova\Core\PushNotificationService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/RecordingDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class FleetReturnedPushServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	private RecordingDatabase $db;

	protected function setUp(): void
	{
		parent::setUp();
		$this->db = new RecordingDatabase();
		$this->swapDatabaseInstance($this->db);
		PushNotificationService::setErrorLogger(static function (): void {});
		PushNotificationService::setConfiguredOverride(null);
	}

	protected function tearDown(): void
	{
		PushNotificationService::setErrorLogger(null);
		PushNotificationService::setConfiguredOverride(null);
		PushNotificationService::setWebPushFactory(null);
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function testFleetReturnedMessageUsesMissionAndHomeCoords(): void
	{
		$message = FleetReturnedPushService::fleetReturnedMessage([
			'fleet_id'           => 99,
			'fleet_owner'        => 7,
			'fleet_mission'      => FLEET_MISSION_ATTACK,
			'fleet_start_galaxy' => 1,
			'fleet_start_system' => 2,
			'fleet_start_planet' => 3,
		], [
			'push_fleet_returned_title' => 'Fleet returned',
			'push_fleet_returned_body'  => '%s returned to [%d:%d:%d]',
			'type_mission_' . FLEET_MISSION_ATTACK => 'Attack',
		]);

		$this->assertNotNull($message);
		$this->assertSame('Fleet returned', $message['title']);
		$this->assertSame('Attack returned to [1:2:3]', $message['body']);
		$this->assertSame('fleet_returned', $message['data']['type']);
		$this->assertSame('game.php?page=fleetTable', $message['data']['url']);
		$this->assertSame('fleet_returned-99', $message['data']['tag']);
		$this->assertSame(FLEET_MISSION_ATTACK, $message['data']['mission']);
	}

	public function testFleetReturnedMessageRejectsIncompleteFleet(): void
	{
		$this->assertNull(FleetReturnedPushService::fleetReturnedMessage([
			'fleet_owner'   => 0,
			'fleet_mission' => FLEET_MISSION_ATTACK,
		]));
		$this->assertNull(FleetReturnedPushService::fleetReturnedMessage([
			'fleet_owner'   => 7,
			'fleet_mission' => 0,
		]));
	}

	public function testShouldNotifyReturnRequiresHomeRestore(): void
	{
		$fleet = [
			'fleet_owner'   => 7,
			'fleet_mission' => FLEET_MISSION_TRANSPORT,
		];

		$this->assertTrue(FleetReturnedPushService::shouldNotifyReturn($fleet, true));
		$this->assertFalse(FleetReturnedPushService::shouldNotifyReturn($fleet, false));
		$this->assertFalse(FleetReturnedPushService::shouldNotifyReturn([
			'fleet_owner'   => 0,
			'fleet_mission' => FLEET_MISSION_TRANSPORT,
		], true));
	}

	public function testNotifyDeliversWhenConfigured(): void
	{
		$sent = [];
		$service = new FleetReturnedPushService(
			notifier: static function (int $userId, string $title, string $body, array $data) use (&$sent): bool {
				$sent[] = compact('userId', 'title', 'body', 'data');

				return true;
			},
			configured: true,
		);

		$this->assertTrue($service->notify([
			'fleet_id'           => 5,
			'fleet_owner'        => 42,
			'fleet_mission'      => FLEET_MISSION_TRANSPORT,
			'fleet_start_galaxy' => 4,
			'fleet_start_system' => 5,
			'fleet_start_planet' => 6,
		], true));

		$this->assertCount(1, $sent);
		$this->assertSame(42, $sent[0]['userId']);
		$this->assertSame('fleet_returned', $sent[0]['data']['type']);
	}

	public function testNotifySkipsDeployAndWhenNotConfigured(): void
	{
		$sent = [];
		$service = new FleetReturnedPushService(
			notifier: static function () use (&$sent): bool {
				$sent[] = 1;

				return true;
			},
			configured: true,
		);

		$this->assertFalse($service->notify([
			'fleet_owner'   => 42,
			'fleet_mission' => FLEET_MISSION_TRANSPORT,
		], false));

		$disabled = new FleetReturnedPushService(
			notifier: static function () use (&$sent): bool {
				$sent[] = 1;

				return true;
			},
			configured: false,
		);
		$this->assertFalse($disabled->notify([
			'fleet_owner'   => 42,
			'fleet_mission' => FLEET_MISSION_TRANSPORT,
		], true));

		$this->assertSame([], $sent);
	}

	public function testRestoreFleetHomeReturnStillCompletesWhenPushNotConfigured(): void
	{
		PushNotificationService::setConfiguredOverride(false);

		$mf = new MissionFunctions();
		$mf->_fleet = [
			'fleet_id'                  => 582249,
			'fleet_owner'               => 42,
			'fleet_mission'             => FLEET_MISSION_ATTACK,
			'fleet_array'               => '',
			'fleet_resource_metal'      => 0,
			'fleet_resource_crystal'    => 0,
			'fleet_resource_deuterium'  => 0,
			'fleet_resource_darkmatter' => 0,
			'fleet_start_id'            => 2172,
			'fleet_end_id'              => 99,
			'fleet_start_galaxy'        => 1,
			'fleet_start_system'        => 2,
			'fleet_start_planet'        => 3,
		];

		$mf->RestoreFleet(true);

		$this->assertSame(1, $mf->kill);
		$this->assertCount(1, $this->db->updates);
		$this->assertCount(1, $this->db->deletes);
	}

	public function testRestoreFleetDeployDoesNotRequireOwnerFieldsForSql(): void
	{
		PushNotificationService::setConfiguredOverride(true);

		$mf = new MissionFunctions();
		$mf->_fleet = [
			'fleet_id'                  => 1,
			'fleet_array'               => '',
			'fleet_resource_metal'      => 0,
			'fleet_resource_crystal'    => 0,
			'fleet_resource_deuterium'  => 0,
			'fleet_resource_darkmatter' => 0,
			'fleet_start_id'            => 2172,
			'fleet_end_id'              => 99,
		];

		$mf->RestoreFleet(false);

		[, $params] = $this->db->updates[0];
		$this->assertSame(99, $params[':planetId']);
		$this->assertSame(1, $mf->kill);
	}
}
