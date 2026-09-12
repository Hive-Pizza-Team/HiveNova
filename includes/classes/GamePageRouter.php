<?php

namespace HiveNova\Core;

/**
 * Sanitize in-game page slugs and resolve player-facing aliases.
 */
class GamePageRouter
{
	/** @var array<string, string> */
	private const ALIASES = [
		'empire' => 'imperium',
	];

	public static function resolve(string $page): string
	{
		$page = str_replace(['_', '\\', '/', '.', "\0"], '', $page);
		$key = strtolower($page);

		return self::ALIASES[$key] ?? $page;
	}
}
