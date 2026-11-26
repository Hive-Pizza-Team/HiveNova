<?php

namespace HiveNova\Core;

class AllianceNameDisplay
{
	/**
	 * Escape an alliance name or tag once for HTML.
	 *
	 * Names and tags are stored after HTTP::_GP(), which already runs
	 * htmlspecialchars(), so this decodes any stored entity layers first.
	 * The result is safe HTML text: a value like <i>Test</i> is shown
	 * literally and is never rendered as markup.
	 */
	public static function plain(mixed $name): string
	{
		if ($name === null || $name === '') {
			return '';
		}

		return htmlspecialchars(self::decode((string) $name), ENT_QUOTES, 'UTF-8');
	}

	public static function decode(string $name): string
	{
		$previous = null;
		for ($i = 0; $i < 5 && $name !== $previous; $i++) {
			$previous = $name;
			$name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}

		return $name;
	}
}
