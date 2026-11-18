<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\ReleaseNotesService;
use HiveNova\Core\ReleaseSeenRepository;

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
			'releases'       => $rows,
			'whatsNewUnseen' => 0,
		]);

		$this->display('page.whatsNew.default.tpl');
	}
}
