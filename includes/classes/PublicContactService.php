<?php

namespace HiveNova\Core;

/**
 * Public Contact Admin (login `disclamer`) display values.
 *
 * Admin config keys stay the 2Moons `disclamer*` spelling. Empty production
 * fields fall back to Discord so the page is not a soft-404.
 */
class PublicContactService
{
	public const DEFAULT_DISCORD_URL = 'https://discord.gg/bP6ksCeEUk';

	public const DEFAULT_NOTICE_FALLBACK = 'Community support is available on Discord.';

	/**
	 * @param array{
	 *     address?: string|null,
	 *     phone?: string|null,
	 *     mail?: string|null,
	 *     notice?: string|null,
	 *     discordUrl?: string|null,
	 *     noticeFallback?: string|null
	 * } $input
	 * @return array{
	 *     address: string,
	 *     phone: string,
	 *     mail: string,
	 *     mailHref: string,
	 *     notice: string,
	 *     discordUrl: string,
	 *     hasContent: bool
	 * }
	 */
	public static function resolve(array $input): array
	{
		$address = self::trimField($input['address'] ?? '');
		$phone = self::trimField($input['phone'] ?? '');
		$mail = self::trimField($input['mail'] ?? '');
		$notice = self::trimField($input['notice'] ?? '');
		$discordUrl = self::trimField($input['discordUrl'] ?? '');
		if ($discordUrl === '') {
			$discordUrl = defined('DISCORD_URL') ? (string) DISCORD_URL : self::DEFAULT_DISCORD_URL;
			$discordUrl = self::trimField($discordUrl);
		}

		$noticeFallback = self::trimField($input['noticeFallback'] ?? '');
		if ($noticeFallback === '') {
			$noticeFallback = self::DEFAULT_NOTICE_FALLBACK;
		}

		if ($notice === '' && ($discordUrl !== '' || $address !== '' || $phone !== '' || $mail !== '')) {
			$notice = $noticeFallback;
		}

		$hasContent = $address !== '' || $phone !== '' || $mail !== '' || $notice !== '' || $discordUrl !== '';

		return [
			'address'    => $address,
			'phone'      => $phone,
			'mail'       => $mail,
			'mailHref'   => self::mailHref($mail),
			'notice'     => $notice,
			'discordUrl' => $discordUrl,
			'hasContent' => $hasContent,
		];
	}

	public static function mailHref(string $mail): string
	{
		$mail = self::trimField($mail);
		if ($mail === '') {
			return '';
		}

		if (str_contains($mail, '://') || str_starts_with(strtolower($mail), 'mailto:')) {
			return $mail;
		}

		return 'mailto:'.$mail;
	}

	private static function trimField(mixed $value): string
	{
		return is_string($value) ? trim($value) : '';
	}
}
