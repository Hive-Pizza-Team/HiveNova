<?php

namespace HiveNova\Core;

/**
 * Reads player-facing release notes from changelog/vX.Y.Z.md files.
 * Pure filesystem logic — no DB or globals.
 */
class ReleaseNotesService
{
	private const FILE_PATTERN = '/^v(\d+)\.(\d+)\.(\d+)\.md$/';
	private const VERSION_PATTERN = '/^v\d+\.\d+\.\d+$/';

	public function __construct(
		private readonly string $changelogDir = ROOT_PATH . 'changelog/',
	) {}

	/**
	 * @return list<array{version: string, items: list<string>}>
	 */
	public function listReleases(): array
	{
		$releases = [];
		foreach ($this->versionFiles() as $version => $path) {
			$releases[] = [
				'version' => $version,
				'items'   => $this->parseItems($path),
			];
		}

		return $releases;
	}

	public function latestVersion(): ?string
	{
		$files = $this->versionFiles();
		if ($files === []) {
			return null;
		}

		return array_key_first($files);
	}

	/**
	 * Versions newer than $lastSeen (newest first).
	 * NULL/''/unparseable $lastSeen means every known version is unseen.
	 *
	 * @return list<string>
	 */
	public function unseenVersions(?string $lastSeen): array
	{
		$versions = array_keys($this->versionFiles());
		if ($versions === []) {
			return [];
		}

		if ($lastSeen === null || $lastSeen === '' || !$this->isParseableVersion($lastSeen)) {
			return $versions;
		}

		$unseen = [];
		foreach ($versions as $version) {
			if (version_compare($version, $lastSeen, '>')) {
				$unseen[] = $version;
			}
		}

		return $unseen;
	}

	public function hasUnseen(?string $lastSeen): bool
	{
		return $this->unseenVersions($lastSeen) !== [];
	}

	public function isParseableVersion(string $version): bool
	{
		return (bool) preg_match(self::VERSION_PATTERN, $version);
	}

	/**
	 * @return array<string, string> version => absolute path, newest first
	 */
	private function versionFiles(): array
	{
		$dir = $this->changelogDir;
		if ($dir === '' || !is_dir($dir)) {
			return [];
		}

		$files = [];
		$entries = scandir($dir);
		if ($entries === false) {
			return [];
		}

		foreach ($entries as $entry) {
			if (!preg_match(self::FILE_PATTERN, $entry, $m)) {
				continue;
			}
			$version = 'v' . $m[1] . '.' . $m[2] . '.' . $m[3];
			$files[$version] = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $entry;
		}

		uksort($files, static fn(string $a, string $b): int => version_compare($b, $a));

		return $files;
	}

	/**
	 * @return list<string>
	 */
	private function parseItems(string $path): array
	{
		$contents = @file_get_contents($path);
		if ($contents === false || $contents === '') {
			return [];
		}

		$items = [];
		foreach (preg_split("/\R/", $contents) as $line) {
			$line = trim($line);
			if (str_starts_with($line, '- ') || str_starts_with($line, '* ')) {
				$items[] = trim(substr($line, 2));
			}
		}

		return $items;
	}
}
