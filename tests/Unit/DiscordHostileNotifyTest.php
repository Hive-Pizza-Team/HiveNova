<?php

use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\DiscordWebhookService;
use HiveNova\Core\FleetFunctions;
use HiveNova\Mission\MissionCaseACS;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DiscordWebhookDatabaseStub.php';

class DiscordHostileNotifyTest extends TestCase
{
	private const VALID_TOKEN = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMN0123456789-_xx';
	private const SNOWFLAKE = '123456789012345678';

	private DiscordWebhookDatabaseStub $db;

	/** @var list<array{url: string, json: string}> */
	private array $posts = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->db = new DiscordWebhookDatabaseStub();
		$this->swapDatabase($this->db);
		$this->posts = [];
		DiscordWebhookService::setPoster(function (string $url, string $json): int {
			$this->posts[] = ['url' => $url, 'json' => $json];
			return 204;
		});
		$this->db->users[2] = ['id' => 2, 'username' => 'Alice', 'ally_id' => 10];
		$this->db->alliances[10] = [
			'id' => 10,
			'ally_discord_webhook' => 'https://discord.com/api/webhooks/' . self::SNOWFLAKE . '/' . self::VALID_TOKEN,
		];
	}

	protected function tearDown(): void
	{
		DiscordWebhookService::setPoster(null);
		$ref = new ReflectionClass(Database::class);
		$prop = $ref->getProperty('instance');
		$prop->setAccessible(true);
		$prop->setValue(null, null);
		parent::tearDown();
	}

	private function swapDatabase(DatabaseInterface $fake): void
	{
		$ref = new ReflectionClass(Database::class);
		$prop = $ref->getProperty('instance');
		$prop->setAccessible(true);
		$prop->setValue(null, $fake);
	}

	private function sendHostile(int $mission, int $startOwner = 1, int $targetOwner = 2): int
	{
		return FleetFunctions::sendFleet(
			[202 => 1],
			$mission,
			$startOwner,
			10,
			1, 1, 1, 1,
			$targetOwner,
			20,
			1, 2, 3, 1,
			[901 => 0, 902 => 0, 903 => 0],
			TIMESTAMP + 100,
			TIMESTAMP + 100,
			TIMESTAMP + 200
		);
	}

	public function testSendFleetAttackPostsIncomingAndStillInsertsWhenPosterThrows(): void
	{
		$fleetId = $this->sendHostile(1);
		$this->assertGreaterThan(0, $fleetId);
		$this->assertCount(1, $this->posts);
		$this->assertGreaterThanOrEqual(3, count($this->db->inserts));

		DiscordWebhookService::setPoster(static function (): int {
			throw new RuntimeException('timeout');
		});
		$this->posts = [];
		$fleetId = $this->sendHostile(1);
		$this->assertGreaterThan(0, $fleetId);
		$this->assertSame([], $this->posts);
	}

	/** @dataProvider hostileMissionProvider */
	public function testSendFleetPostsIncomingForHostileMissions(int $mission): void
	{
		$this->posts = [];
		$this->sendHostile($mission);
		$this->assertCount(1, $this->posts, 'mission ' . $mission);
	}

	public static function hostileMissionProvider(): array
	{
		return [
			'attack' => [1],
			'acs'    => [2],
			'spy'    => [6],
			'destroy'=> [9],
			'missile'=> [10],
		];
	}

	public function testSendFleetNpcRaidNamesPiratesInDiscordEmbed(): void
	{
		$this->posts = [];
		$this->sendHostile(1, 0, 2);
		$this->assertCount(1, $this->posts);
		$embed = json_decode($this->posts[0]['json'], true)['embeds'][0];
		$this->assertStringContainsString('Pirates', $embed['title']);
		$this->assertStringContainsString('Pirates', $embed['description']);
	}

	public function testSendFleetTransportDoesNotPost(): void
	{
		$this->sendHostile(3);
		$this->assertSame([], $this->posts);
	}

	public function testSendFleetSelfTargetDoesNotPost(): void
	{
		$this->sendHostile(1, 2, 2);
		$this->assertSame([], $this->posts);
	}

	public function testAcsTargetEventDoesNotPostCombat(): void
	{
		$mission = new MissionCaseACS([
			'fleet_id' => 5,
			'fleet_mission' => 2,
			'fleet_owner' => 1,
			'fleet_target_owner' => 2,
			'fleet_end_galaxy' => 1,
			'fleet_end_system' => 2,
			'fleet_end_planet' => 3,
			'fleet_end_type' => 1,
			'fleet_end_time' => TIMESTAMP + 200,
		]);
		$mission->TargetEvent();
		$this->assertSame([], $this->posts);
	}

	public function testCombatNotifySkippedWhenDefenderLeftAlliance(): void
	{
		$this->db->users[2]['ally_id'] = 0;
		DiscordWebhookService::notifyCombatResolved(2, 1, 1, 2, 3, 1);
		$this->assertSame([], $this->posts);
	}

	private function missionSource(string $file): string
	{
		return file_get_contents(__DIR__ . '/../../includes/classes/missions/' . $file);
	}

	public function testSpyMissionClassDoesNotCallCombatNotify(): void
	{
		$src = $this->missionSource('MissionCaseSpy.php');
		$this->assertStringNotContainsString('notifyCombatResolved', $src);
		$this->assertStringNotContainsString('DiscordWebhookService', $src);
	}

	public function testAttackAndDestructionCallCombatNotifyAfterSave(): void
	{
		$attack = $this->missionSource('MissionCaseAttack.php');
		$destroy = $this->missionSource('MissionCaseDestruction.php');
		$mip = $this->missionSource('MissionCaseMIP.php');
		$this->assertStringContainsString('notifyCombatResolved', $attack);
		$this->assertStringContainsString('notifyCombatResolved', $destroy);
		$this->assertStringContainsString('notifyCombatResolved', $mip);
		$this->assertGreaterThan(
			strpos($attack, 'SaveFleet'),
			strpos($attack, 'notifyCombatResolved')
		);
	}
}
