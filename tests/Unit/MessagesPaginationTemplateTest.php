<?php

use HiveNova\Core\Template;
use PHPUnit\Framework\TestCase;

class MessagesPaginationTemplateTest extends TestCase
{
	private function renderMessagesPage(array $overrides = []): string
	{
		$template = new Template();
		$template->setCaching(\Smarty::CACHING_OFF);
		$template->setForceCompile(true);
		$template->setTemplateDir(ROOT_PATH . 'styles/templates/game/');

		$template->assign(array_merge([
			'LNG' => [
				'lm_messages' => 'Messages',
				'mg_overview' => 'Overview',
				'loading' => 'Loading',
				'mg_type' => [1 => 'Players', 100 => 'All'],
				'mg_message_title' => 'Inbox',
				'mg_filter_all' => 'All',
				'mg_filter_lost' => 'Lost',
				'mg_read_marked' => 'Read marked',
				'mg_read_type_all' => 'Read type',
				'mg_read_all' => 'Read all',
				'mg_delete_marked' => 'Delete marked',
				'mg_delete_unmarked' => 'Delete unmarked',
				'mg_delete_type_all' => 'Delete type',
				'mg_delete_all' => 'Delete all',
				'mg_confirm' => 'OK',
				'mg_action' => 'Action',
				'mg_date' => 'Date',
				'mg_from' => 'From',
				'mg_to' => 'To',
				'mg_subject' => 'Subject',
				'mg_page' => 'Page',
				'mg_no_messages' => 'No messages',
				'mg_answer_to' => 'Reply to',
			],
			'CategoryList' => [
				1 => ['color' => '#fff', 'unread' => 0, 'total' => 12],
			],
			'MessID' => 1,
			'page' => 2,
			'maxPage' => 3,
			'lostFilter' => false,
			'canFilterLost' => false,
			'filterQuery' => '',
			'MessageCount' => 12,
			'MessageList' => [
				[
					'id' => 10,
					'unread' => 0,
					'time' => '2026-09-13',
					'from' => 'Fleet',
					'subject' => 'Spy',
					'type' => 0,
					'sender' => 0,
					'text' => 'Report body',
				],
			],
		], $overrides));

		return $template->fetch('extends:layout.ajax.tpl|page.messages.default.tpl');
	}

	public function testMultiPageInboxRendersMatchingPaginationAtTopAndBottom(): void
	{
		$html = $this->renderMessagesPage();

		preg_match_all('/<tr class="msg-pagination-row">.*?<\/tr>/s', $html, $rows);
		$this->assertCount(2, $rows[0], $html);
		$this->assertSame($rows[0][0], $rows[0][1]);
		$this->assertStringContainsString('Page:', $rows[0][0]);
		$this->assertStringContainsString('game.php?page=messages&category=1&side=1', $rows[0][0]);
		$this->assertStringContainsString('game.php?page=messages&category=1&side=3', $rows[0][0]);
		$this->assertStringContainsString('<b>[2]&nbsp;</b>', $rows[0][0]);

		$top = strpos($html, $rows[0][0]);
		$message = strpos($html, 'id="message_10"');
		$bottom = strrpos($html, $rows[0][1]);

		$this->assertNotFalse($top);
		$this->assertNotFalse($message);
		$this->assertNotFalse($bottom);
		$this->assertLessThan($message, $top);
		$this->assertGreaterThan($message, $bottom);
	}

	public function testSinglePageInboxHidesPagination(): void
	{
		$html = $this->renderMessagesPage([
			'page' => 1,
			'maxPage' => 1,
			'MessageCount' => 3,
		]);

		$this->assertStringNotContainsString('msg-pagination-row', $html);
		$this->assertStringContainsString('id="message_10"', $html);
	}

	public function testLostFilterIsKeptOnBothPaginators(): void
	{
		$html = $this->renderMessagesPage([
			'lostFilter' => true,
			'canFilterLost' => true,
			'filterQuery' => '&filter=lost',
		]);

		preg_match_all('/<tr class="msg-pagination-row">.*?<\/tr>/s', $html, $rows);
		$this->assertCount(2, $rows[0], $html);
		$this->assertSame($rows[0][0], $rows[0][1]);
		$this->assertStringContainsString('game.php?page=messages&category=1&filter=lost&side=3', $rows[0][0]);
	}
}
