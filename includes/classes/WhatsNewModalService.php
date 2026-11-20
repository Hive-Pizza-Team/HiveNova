<?php

namespace HiveNova\Core;

/**
 * Decides whether the one-time What's New release modal should appear.
 */
class WhatsNewModalService
{
	public function __construct(
		private readonly ReleaseNotesService $notes = new ReleaseNotesService(),
	) {}

	/**
	 * Unseen releases newest-first, or empty when the modal must not show.
	 *
	 * @return list<array{version: string, items: list<string>}>
	 */
	public function modalReleases(?string $lastSeen, string $page, bool $celebrationPending): array
	{
		if ($celebrationPending) {
			return [];
		}

		if ($page === 'whatsNew') {
			return [];
		}

		$unseenVersions = $this->notes->unseenVersions($lastSeen);
		if ($unseenVersions === []) {
			return [];
		}

		$unseenSet = array_fill_keys($unseenVersions, true);
		$result = [];
		foreach ($this->notes->listReleases() as $release) {
			if (isset($unseenSet[$release['version']])) {
				$result[] = $release;
			}
		}

		return $result;
	}
}
