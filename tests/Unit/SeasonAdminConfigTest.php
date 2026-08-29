<?php

use HiveNova\Core\SeasonAdminConfig;

use PHPUnit\Framework\TestCase;

class SeasonAdminConfigTest extends TestCase
{
	public function testEmptyPostedKeyIsOmitted(): void
	{
		$result = SeasonAdminConfig::applyPosted(
			['season_wallet_active_key' => '5KEXISTING', 'season_blog_posting_key' => '5KBLOGOLD', 'season_mode' => 0],
			['season_mode' => 'on', 'season_wallet_active_key' => '', 'season_blog_posting_key' => '']
		);
		$this->assertArrayNotHasKey('season_wallet_active_key', $result['apply']);
		$this->assertArrayNotHasKey('season_blog_posting_key', $result['apply']);
		$this->assertSame(1, $result['apply']['season_mode']);
	}

	public function testPostedKeyReplacesStoredKey(): void
	{
		$GLOBALS['salt'] = './0123456789abcdefghij';
		$result = SeasonAdminConfig::applyPosted(
			['season_wallet_active_key' => '5KOLD', 'season_blog_posting_key' => '5KBLOGOLD'],
			['season_wallet_active_key' => '5KNEW', 'season_blog_posting_key' => '5KBLOGNEW']
		);
		$wallet = $result['apply']['season_wallet_active_key'];
		$blog = $result['apply']['season_blog_posting_key'];
		$this->assertStringStartsWith('enc:v1:', $wallet);
		$this->assertStringStartsWith('enc:v1:', $blog);
		$this->assertSame('5KNEW', \HiveNova\Core\ConfigSecret::reveal($wallet));
		$this->assertSame('5KBLOGNEW', \HiveNova\Core\ConfigSecret::reveal($blog));
	}

	public function testLogNeverContainsRawWif(): void
	{
		$result = SeasonAdminConfig::applyPosted(
			['season_wallet_active_key' => '5KSECRETOLD', 'season_blog_posting_key' => '5KBLOGSECRETOLD'],
			['season_wallet_active_key' => '5KSECRETNEW', 'season_blog_posting_key' => '5KBLOGSECRETNEW']
		);
		$blob = json_encode([$result['log_old'], $result['log_new'], $result['template']]);
		$this->assertStringNotContainsString('5KSECRETOLD', (string) $blob);
		$this->assertStringNotContainsString('5KSECRETNEW', (string) $blob);
		$this->assertStringNotContainsString('5KBLOGSECRETOLD', (string) $blob);
		$this->assertStringNotContainsString('5KBLOGSECRETNEW', (string) $blob);
		$this->assertSame('', $result['template']['season_wallet_active_key']);
		$this->assertSame('', $result['template']['season_blog_posting_key']);
	}

	public function testCutAndEntryBounds(): void
	{
		$result = SeasonAdminConfig::applyPosted([], [
			'season_house_cut_percent' => '12.5',
			'season_entry_pizza' => '0.250',
			'season_min_points' => '100',
			'season_length_seconds' => '604800',
			'season_preclose_seconds' => '14400',
			'season_wallet_account' => 'season.wallet',
			'season_blog_account' => 'Season.Blog',
		]);
		$this->assertSame('12.50', $result['apply']['season_house_cut_percent']);
		$this->assertSame('0.25', $result['apply']['season_entry_pizza']);
		$this->assertSame(100, $result['apply']['season_min_points']);
		$this->assertSame('season.wallet', $result['apply']['season_wallet_account']);
		$this->assertSame('season.blog', $result['apply']['season_blog_account']);
	}

	public function testInvalidCutIsRejected(): void
	{
		$result = SeasonAdminConfig::applyPosted([], ['season_house_cut_percent' => '140']);
		$this->assertArrayNotHasKey('season_house_cut_percent', $result['apply']);
	}

	public function testEnablingAppliesEightXWhenSpeedsAreDefault(): void
	{
		$result = SeasonAdminConfig::applyPosted(
			['season_mode' => 0, 'game_speed' => 2500, 'fleet_speed' => 2500, 'resource_multiplier' => 1],
			['season_mode' => 'on']
		);
		$this->assertSame(20000, $result['apply']['game_speed']);
		$this->assertSame(20000, $result['apply']['fleet_speed']);
		$this->assertSame(8, $result['apply']['resource_multiplier']);
	}

	public function testEnablingLeavesCustomSpeedsAlone(): void
	{
		$result = SeasonAdminConfig::applyPosted(
			['season_mode' => 0, 'game_speed' => 5000, 'fleet_speed' => 5000, 'resource_multiplier' => 4],
			['season_mode' => 'on']
		);
		$this->assertArrayNotHasKey('game_speed', $result['apply']);
		$this->assertArrayNotHasKey('resource_multiplier', $result['apply']);
	}
}
