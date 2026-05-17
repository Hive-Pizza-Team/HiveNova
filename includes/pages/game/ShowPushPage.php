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

		$raw = file_get_contents('php://input');
		$data = json_decode($raw ?: '', true);
		if (!is_array($data) || empty($data['endpoint']) || empty($data['keys']['p256dh']) || empty($data['keys']['auth'])) {
			HTTP::sendHeader('HTTP/1.1 400 Bad Request');
			$this->sendJSON(['error' => 'invalid_subscription']);
		}

		PushNotificationService::setUserPreference((int) $USER['id'], true);
		PushNotificationService::saveSubscription(
			(int) $USER['id'],
			$data,
			$_SERVER['HTTP_USER_AGENT'] ?? null
		);

		$this->sendJSON(['ok' => true]);
	}

	public function unsubscribe()
	{
		global $USER;

		$raw = file_get_contents('php://input');
		$data = json_decode($raw ?: '', true);
		if (!empty($data['endpoint'])) {
			PushNotificationService::removeSubscription($data['endpoint']);
		}

		PushNotificationService::setUserPreference((int) $USER['id'], false);

		$this->sendJSON(['ok' => true]);
	}
}
