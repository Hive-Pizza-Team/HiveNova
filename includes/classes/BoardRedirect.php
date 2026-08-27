<?php

namespace HiveNova\Core;

class BoardRedirect
{
	/**
	 * @return string|null Valid forum URL, or null if unset/invalid
	 */
	public static function forumUrl(): ?string
	{
		$boardUrl = Config::get()->forum_url;
		if (!is_string($boardUrl) || $boardUrl === '') {
			return null;
		}

		return filter_var($boardUrl, FILTER_VALIDATE_URL) ? $boardUrl : null;
	}

	public static function redirectIfConfigured(): bool
	{
		$url = self::forumUrl();
		if ($url === null) {
			return false;
		}

		HTTP::sendHeader('Location', $url);
		return true;
	}
}
