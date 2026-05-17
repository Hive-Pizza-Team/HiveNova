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
		$this->vapidPublicKey();
	}

	public function vapidPublicKey()
	{
		$this->sendJSON([
			'configured' => PushNotificationService::isConfigured(),
			'publicKey'  => PushNotificationService::getPublicKey(),
		]);
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

		PushNotificationService::saveSubscription(
			(int) $USER['id'],
			$data,
			$_SERVER['HTTP_USER_AGENT'] ?? null
		);

		$this->sendJSON(['ok' => true]);
	}

	public function unsubscribe()
	{
		$raw = file_get_contents('php://input');
		$data = json_decode($raw ?: '', true);
		if (!empty($data['endpoint'])) {
			PushNotificationService::removeSubscription($data['endpoint']);
		}
		$this->sendJSON(['ok' => true]);
	}
}
