<?php

namespace HiveNova\Cronjob;

use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
use HiveNova\Core\Config;
use HiveNova\Core\Language;
use HiveNova\Core\PlayerUtil;
use HiveNova\Cronjob\CronjobTask;
use UnexpectedValueException;

/**
 *  2Moons
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 */


class ReferralCronjob implements CronjobTask
{
	function run()
	{
		/** @var $langObjects Language[] */
		$langObjects	= array();

		$db	= Database::get();

		$sql	= 'SELECT user.`username`, user.`ref_id`, user.`id`, user.`lang` as recruit_lang,
			ref_users.`lang` as referrer_lang, user.`universe`, stats.`total_points`
		FROM %%USERS%% user
		INNER JOIN %%USERS%% as ref_users
		ON ref_users.`id` = user.`ref_id`
		INNER JOIN %%STATPOINTS%% as stats
		ON stats.`id_owner` = user.`id` AND stats.`stat_type` = :type
		WHERE user.`ref_bonus` = 1;';

		$userArray	= $db->select($sql, array(
			':type'		=> 1,
		));

		foreach($userArray as $user)
		{
			try {
				$userConfig	= Config::get((int) $user['universe']);
			} catch (\Exception) {
				continue;
			}

			if ((int) $userConfig->ref_active !== 1) {
				continue;
			}

			if ((int) $user['total_points'] < (int) $userConfig->ref_minpoints) {
				continue;
			}

			$referrerBonus	= (int) $userConfig->ref_bonus;
			$recruitBonus	= $this->refereeBonusAmount($userConfig);

			if ($referrerBonus > 0) {
				$this->addDarkMatter($db, (int) $user['ref_id'], $referrerBonus);
			}
			if ($recruitBonus > 0) {
				$this->addDarkMatter($db, (int) $user['id'], $recruitBonus);
			}

			$sql	= 'UPDATE %%USERS%% SET `ref_bonus` = 0 WHERE `id` = :userId;';
			$db->update($sql, array(
				':userId'	=> $user['id']
			));

			$referrerLng	= $this->languageFor($langObjects, (string) $user['referrer_lang']);
			$recruitLng	= $this->languageFor($langObjects, (string) $user['recruit_lang']);
			$universe	= (int) $user['universe'];
			$pointsLabel	= pretty_number($userConfig->ref_minpoints);
			$pizzabitsName	= $referrerLng['tech'][921];

			if ($referrerBonus > 0) {
				$Message	= sprintf(
					$referrerLng['sys_refferal_text'],
					$user['username'],
					$pointsLabel,
					pretty_number($referrerBonus),
					$pizzabitsName
				);
				PlayerUtil::sendMessage(
					(int) $user['ref_id'],
					0,
					$referrerLng['sys_refferal_from'],
					4,
					sprintf($referrerLng['sys_refferal_title'], $user['username']),
					$Message,
					TIMESTAMP,
					null,
					1,
					$universe
				);
			}

			if ($recruitBonus > 0) {
				$pizzabitsRecruit	= $recruitLng['tech'][921];
				$Message	= sprintf(
					$recruitLng['sys_refferal_recruit_text'],
					$pointsLabel,
					pretty_number($recruitBonus),
					$pizzabitsRecruit
				);
				PlayerUtil::sendMessage(
					(int) $user['id'],
					0,
					$recruitLng['sys_refferal_from'],
					4,
					$recruitLng['sys_refferal_recruit_title'],
					$Message,
					TIMESTAMP,
					null,
					1,
					$universe
				);
			}
		}

		return true;
	}

	private function refereeBonusAmount(Config $userConfig): int
	{
		try {
			return (int) $userConfig->ref_bonus_referee;
		} catch (UnexpectedValueException) {
			return 0;
		}
	}

	private function addDarkMatter(DatabaseInterface $db, int $userId, int $bonus): void
	{
		$sql	= 'UPDATE %%USERS%% SET `darkmatter` = `darkmatter` + :bonus WHERE `id` = :userId;';
		$db->update($sql, array(
			':bonus'	=> $bonus,
			':userId'	=> $userId
		));
	}

	/**
	 * @param array<string, Language> $langObjects
	 */
	private function languageFor(array &$langObjects, string $lang): Language
	{
		if ($lang === '') {
			$lang	= 'en';
		}
		if (!isset($langObjects[$lang])) {
			$langObjects[$lang]	= new Language($lang);
			$langObjects[$lang]->includeData(array('L18N', 'INGAME', 'TECH', 'CUSTOM'));
		}

		return $langObjects[$lang];
	}
}
