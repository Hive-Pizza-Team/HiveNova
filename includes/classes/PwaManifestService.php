<?php

namespace HiveNova\Core;

/**
 * Builds the Web App Manifest advertised on lobby and in-game pages.
 */
class PwaManifestService
{
	public const FALLBACK_NAME = 'HiveNova';
	public const THEME_COLOR = '#1a1a2e';
	public const SHORT_NAME_MAX = 12;
	public const ASSET_DIR = 'styles/resource/images/pwa/';

	/**
	 * @return array<string, mixed>
	 */
	public function build(string $gameName, string $httpRoot = '/'): array
	{
		$name = $this->displayName($gameName);
		$root = $this->normalizeRoot($httpRoot);

		return [
			'name'             => $name,
			'short_name'       => $this->shortName($name),
			'description'      => $name . ' — space empire browser game',
			'id'               => $root,
			'start_url'        => $root . 'game.php?page=overview',
			'scope'            => $root,
			'display'          => 'standalone',
			'background_color' => self::THEME_COLOR,
			'theme_color'      => self::THEME_COLOR,
			'orientation'      => 'any',
			'icons'            => $this->icons($root),
			'screenshots'      => $this->screenshots($root, $name),
		];
	}

	public function displayName(string $gameName): string
	{
		$name = trim($gameName);
		return $name !== '' ? $name : self::FALLBACK_NAME;
	}

	public function shortName(string $gameName): string
	{
		$name = $this->displayName($gameName);
		if (function_exists('mb_strlen') && function_exists('mb_substr')) {
			if (mb_strlen($name, 'UTF-8') > self::SHORT_NAME_MAX) {
				return mb_substr($name, 0, self::SHORT_NAME_MAX, 'UTF-8');
			}
			return $name;
		}
		if (strlen($name) > self::SHORT_NAME_MAX) {
			return substr($name, 0, self::SHORT_NAME_MAX);
		}
		return $name;
	}

	public function normalizeRoot(string $httpRoot): string
	{
		$root = str_replace('\\', '/', trim($httpRoot));
		if ($root === '') {
			return '/';
		}
		if ($root[0] !== '/') {
			$root = '/' . $root;
		}
		if (substr($root, -1) !== '/') {
			$root .= '/';
		}
		return $root;
	}

	/**
	 * @return list<array<string, string>>
	 */
	public function icons(string $httpRoot = '/'): array
	{
		$root = $this->normalizeRoot($httpRoot);
		$dir = $root . self::ASSET_DIR;

		return [
			[
				'src'     => $dir . 'icon-192.png',
				'sizes'   => '192x192',
				'type'    => 'image/png',
				'purpose' => 'any',
			],
			[
				'src'     => $dir . 'icon-512.png',
				'sizes'   => '512x512',
				'type'    => 'image/png',
				'purpose' => 'any',
			],
			[
				'src'     => $dir . 'icon-maskable-192.png',
				'sizes'   => '192x192',
				'type'    => 'image/png',
				'purpose' => 'maskable',
			],
			[
				'src'     => $dir . 'icon-maskable-512.png',
				'sizes'   => '512x512',
				'type'    => 'image/png',
				'purpose' => 'maskable',
			],
		];
	}

	/**
	 * @return list<array<string, string>>
	 */
	public function screenshots(string $httpRoot = '/', string $label = self::FALLBACK_NAME): array
	{
		$root = $this->normalizeRoot($httpRoot);
		$dir = $root . self::ASSET_DIR;
		$caption = $this->displayName($label);

		return [
			[
				'src'         => $dir . 'screenshot-wide.png',
				'sizes'       => '1024x768',
				'type'        => 'image/png',
				'form_factor' => 'wide',
				'label'       => $caption,
			],
			[
				'src'         => $dir . 'screenshot-narrow.png',
				'sizes'       => '768x1024',
				'type'        => 'image/png',
				'form_factor' => 'narrow',
				'label'       => $caption,
			],
		];
	}
}
