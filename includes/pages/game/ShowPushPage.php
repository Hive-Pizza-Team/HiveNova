<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\HTTP;
use HiveNova\Core\PushNotificationService;

class ShowPushPage extends AbstractGamePage
{
	public static $requireModule = 0;

	public function __construct()
	{
		parent::__construct();
	}

	public function show()
	{
		$this->status();
	}

	public function status()
	{
		global $USER;

		$this->sendJSON([
			'configured' => PushNotificationService::isConfigured(),
			'publicKey'  => PushNotificationService::getPublicKey(),
			'enabled'    => PushNotificationService::isEnabledForUser((int) $USER['id']),
		]);
	}

	public function vapidPublicKey()
	{
		$this->status();
	}

	public function subscribe()
	{
		global $USER;

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			HTTP::sendHeader('HTTP/1.1 405 Method Not Allowed');
			$this->sendJSON(['error' => 'method_not_allowed']);
			return;
		}

		$raw = $this->readSubscribeBody();
		if (trim($raw) === '') {
			HTTP::sendHeader('HTTP/1.1 400 Bad Request');
			$this->sendJSON(['error' => 'empty_body']);
			return;
		}

		$data = json_decode($raw, true);
		if (!is_array($data)) {
			HTTP::sendHeader('HTTP/1.1 400 Bad Request');
			$this->sendJSON(['error' => 'invalid_json']);
			return;
		}

		if (!PushNotificationService::isValidSubscription($data)) {
			HTTP::sendHeader('HTTP/1.1 400 Bad Request');
			$this->sendJSON(['error' => 'invalid_subscription']);
			return;
		}

		PushNotificationService::saveSubscription(
			(int) $USER['id'],
			$data,
			$_SERVER['HTTP_USER_AGENT'] ?? null
		);

		PushNotificationService::setUserPreference((int) $USER['id'], true);

		$this->sendJSON(['ok' => true]);
	}

	protected function readSubscribeBody(): string
	{
		$raw = file_get_contents('php://input');

		return is_string($raw) ? $raw : '';
	}

	public function unsubscribe()
	{
		global $USER;

		$raw = file_get_contents('php://input');
		$data = json_decode($raw ?: '', true);
		if (!empty($data['endpoint']) && is_string($data['endpoint'])) {
			PushNotificationService::removeSubscriptionForUser((int) $USER['id'], $data['endpoint']);
		}

		PushNotificationService::setUserPreference((int) $USER['id'], false);

		$this->sendJSON(['ok' => true]);
	}
}
