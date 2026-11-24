<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\DirectiveService;
use HiveNova\Core\HTTP;
use HiveNova\Core\ReleaseNotesService;
use HiveNova\Core\ReleaseSeenRepository;
use HiveNova\Core\WhatsNewCsrf;

class ShowWhatsNewPage extends AbstractGamePage
{
	public static $requireModule = 0;

	function __construct()
	{
		parent::__construct();
	}

	function show()
	{
		global $USER;

		$notes = new ReleaseNotesService();
		$releases = $notes->listReleases();
		$unseenSet = [];
		if (array_key_exists('last_seen_release', $USER)) {
			$lastSeen = $USER['last_seen_release'] !== null ? (string) $USER['last_seen_release'] : null;
			$unseenSet = array_fill_keys($notes->unseenVersions($lastSeen), true);
		}

		$rows = [];
		foreach ($releases as $release) {
			$rows[] = [
				'version' => $release['version'],
				'items'   => $release['items'],
				'unseen'  => isset($unseenSet[$release['version']]),
			];
		}

		$latest = $notes->latestVersion();
		if (array_key_exists('last_seen_release', $USER)) {
			$previous = $USER['last_seen_release'] !== null ? (string) $USER['last_seen_release'] : null;
			(new ReleaseSeenRepository())->markSeen((int) $USER['id'], $latest, $previous);
			if ($latest !== null) {
				$USER['last_seen_release'] = $latest;
			}
		}

		$this->assign([
			'releases' => $rows,
		]);

		$this->display('page.whatsNew.default.tpl');
	}

	function dismiss()
	{
		global $USER;

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			HTTP::sendHeader('HTTP/1.1 405 Method Not Allowed');
			$this->sendJSON(['ok' => false, 'error' => 'method_not_allowed']);
			return;
		}

		if (!DirectiveService::isSameOriginRequest()) {
			HTTP::sendHeader('HTTP/1.1 403 Forbidden');
			$this->sendJSON(['ok' => false, 'error' => 'cross_origin']);
			return;
		}

		$token = HTTP::_GP('token', '');
		if (!WhatsNewCsrf::validateCsrfToken($token)) {
			HTTP::sendHeader('HTTP/1.1 403 Forbidden');
			$this->sendJSON(['ok' => false, 'error' => 'invalid_token']);
			return;
		}

		if (!array_key_exists('last_seen_release', $USER)) {
			$this->sendJSON(['ok' => false]);
			return;
		}

		$latest = (new ReleaseNotesService())->latestVersion();
		$previous = $USER['last_seen_release'] !== null ? (string) $USER['last_seen_release'] : null;
		(new ReleaseSeenRepository())->markSeen((int) $USER['id'], $latest, $previous);
		if ($latest !== null) {
			$USER['last_seen_release'] = $latest;
		}

		$this->sendJSON(['ok' => true, 'seen' => $latest]);
	}
}
