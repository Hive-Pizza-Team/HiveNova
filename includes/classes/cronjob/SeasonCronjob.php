<?php

namespace HiveNova\Cronjob;

use HiveNova\Core\DatabaseSeasonStore;
use HiveNova\Core\Language;
use HiveNova\Core\SeasonService;
use HiveNova\Cronjob\CronjobTask;
use Throwable;

class SeasonCronjob implements CronjobTask
{
	public function __construct(
		private readonly ?SeasonService $service = null,
	) {
	}

	public function run()
	{
		try {
			$service = $this->service ?? new SeasonService(new DatabaseSeasonStore());
			$service->tick($this->englishIngameStrings());
		} catch (Throwable $e) {
			return;
		}
	}

	/**
	 * @return array<string, string>
	 */
	private function englishIngameStrings(): array
	{
		try {
			$lang = new Language('en');
			$lang->includeData(['INGAME']);
			$keys = [
				'season_news_start',
				'season_news_daily',
				'season_news_preclose',
				'season_news_close',
				'season_msg_subject',
				'season_wipe_close_reason',
				'season_discord_wipe_start',
				'season_discord_wipe_done',
				'season_discord_payouts',
				'season_discord_blog',
			];
			$out = [];
			foreach ($keys as $key) {
				if (isset($lang[$key])) {
					$out[$key] = (string) $lang[$key];
				}
			}

			return $out;
		} catch (Throwable) {
			return [];
		}
	}
}
