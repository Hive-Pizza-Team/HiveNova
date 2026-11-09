<?php

namespace HiveNova\Core;

use Throwable;

/**
 * Web Push when a fleet restores to its home planet (ReturnEvent / RestoreFleet).
 *
 * Incoming hostile fleets and expedition EndStay results stay on their own
 * notify helpers; this covers the physical return of ships to the owner.
 * Deploy / transfer RestoreFleet($onStart = false) is intentionally skipped.
 */
class FleetReturnedPushService
{
	public const NOTIFY_URL = 'game.php?page=fleetTable';

	/**
	 * @param callable(int, string, string, array<string, mixed>): (bool|void)|null $notifier
	 */
	public function __construct(
		private readonly mixed $notifier = null,
		private readonly ?bool $configured = null,
	) {
	}

	public function isConfigured(): bool
	{
		return $this->configured ?? PushNotificationService::isConfigured();
	}

	/**
	 * @param array<string, mixed> $fleet
	 * @param array<string, mixed>|null $lng
	 * @return array{title: string, body: string, data: array<string, mixed>}|null
	 */
	public static function fleetReturnedMessage(array $fleet, ?array $lng = null): ?array
	{
		$userId = (int) ($fleet['fleet_owner'] ?? 0);
		$mission = (int) ($fleet['fleet_mission'] ?? 0);
		$galaxy = (int) ($fleet['fleet_start_galaxy'] ?? 0);
		$system = (int) ($fleet['fleet_start_system'] ?? 0);
		$planet = (int) ($fleet['fleet_start_planet'] ?? 0);
		$fleetId = (int) ($fleet['fleet_id'] ?? 0);

		if ($userId <= 0 || $mission <= 0) {
			return null;
		}

		$lng = $lng ?? (isset($GLOBALS['LNG']) && is_array($GLOBALS['LNG']) ? $GLOBALS['LNG'] : []);
		$missionName = is_string($lng['type_mission_' . $mission] ?? null)
			? $lng['type_mission_' . $mission]
			: ('Mission ' . $mission);
		$title = is_string($lng['push_fleet_returned_title'] ?? null)
			? $lng['push_fleet_returned_title']
			: 'Fleet returned';
		$template = is_string($lng['push_fleet_returned_body'] ?? null)
			? $lng['push_fleet_returned_body']
			: '%s returned to [%d:%d:%d]';
		$body = sprintf($template, $missionName, $galaxy, $system, $planet);

		return [
			'title' => $title,
			'body'  => $body,
			'data'  => [
				'url'     => self::NOTIFY_URL,
				'type'    => 'fleet_returned',
				'mission' => $mission,
				'tag'     => 'fleet_returned-' . max(0, $fleetId),
			],
		];
	}

	/**
	 * @param array<string, mixed> $fleet
	 */
	public static function shouldNotifyReturn(array $fleet, bool $onStart): bool
	{
		if (!$onStart) {
			return false;
		}

		$userId = (int) ($fleet['fleet_owner'] ?? 0);
		$mission = (int) ($fleet['fleet_mission'] ?? 0);

		return $userId > 0 && $mission > 0;
	}

	/**
	 * @param array<string, mixed> $fleet
	 */
	public static function notifyFleetReturned(array $fleet, bool $onStart = true): bool
	{
		return (new self())->notify($fleet, $onStart);
	}

	/**
	 * @param array<string, mixed> $fleet
	 */
	public function notify(array $fleet, bool $onStart = true): bool
	{
		if (!$this->isConfigured() || !self::shouldNotifyReturn($fleet, $onStart)) {
			return false;
		}

		try {
			$message = self::fleetReturnedMessage($fleet);
			if ($message === null) {
				return false;
			}

			$userId = (int) $fleet['fleet_owner'];

			return $this->deliver($userId, $message);
		} catch (Throwable $e) {
			PushNotificationService::logFailure('FleetReturnedPushService::notify: ' . $e->getMessage());

			return false;
		}
	}

	/**
	 * @param array{title: string, body: string, data: array<string, mixed>} $message
	 */
	private function deliver(int $userId, array $message): bool
	{
		if (is_callable($this->notifier)) {
			return ($this->notifier)($userId, $message['title'], $message['body'], $message['data']) !== false;
		}

		$result = PushNotificationService::notifyUser($userId, $message['title'], $message['body'], $message['data']);

		return !empty($result['ok']);
	}
}
