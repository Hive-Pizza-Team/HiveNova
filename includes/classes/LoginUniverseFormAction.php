<?php

namespace HiveNova\Core;

/**
 * Multi-universe login form action URLs.
 *
 * Login may POST into /uniN/ rewrite paths so the session lands in the chosen
 * universe. Registration must stay on the public lobby path — universe is
 * selected with the `uni` body field (Universe::current() already prefers
 * $_REQUEST['uni']). Rewriting register to /uniN/ leaked internal ids such as
 * /uni6/ for the dropdown labeled "Universe 3" (#377).
 */
class LoginUniverseFormAction
{
	public const ATTR_UNIVERSE_PATH = 'data-universe-path';

	/**
	 * Whether a login-site page should rewrite form actions into /uniN/.
	 */
	public static function usesUniversePath(string $page): bool
	{
		$page = strtolower(trim($page));

		return $page !== 'register';
	}

	/**
	 * HTML attribute value for forms: "1" rewrites to /uniN/, "0" keeps lobby action.
	 */
	public static function universePathAttribute(string $page): string
	{
		return self::usesUniversePath($page) ? '1' : '0';
	}

	/**
	 * Build the form action URL used after a universe select change.
	 *
	 * When $useUniversePath is false (register), returns the relative lobby
	 * action unchanged so the browser stays on /index.php?page=register.
	 */
	public static function build(
		string $basePath,
		string $relativeAction,
		int $universeId,
		bool $isMultiUniverse,
		bool $unisWildcast,
		bool $useUniversePath
	): string {
		$relativeAction = ltrim($relativeAction, '/');
		if ($relativeAction === '') {
			$relativeAction = 'index.php';
		}

		if (!$isMultiUniverse || !$useUniversePath || $universeId <= 0) {
			return $relativeAction;
		}

		$basePath = rtrim($basePath, '/').'/';
		if ($unisWildcast) {
			return str_replace('://', '://uni'.$universeId.'.', $basePath).$relativeAction;
		}

		return $basePath.'uni'.$universeId.'/'.$relativeAction;
	}
}
