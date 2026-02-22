<?php

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

if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) throw new Exception("Permission error!");

function ShowNewsPage(){
	global $LNG, $USER;

	if($_POST['action'] == 'send') {
		$edit_id 	= HTTP::_GP('id', 0);
		$title 		= HTTP::_GP('title', '', true);
		$text 		= HTTP::_GP('text', '', true);
		$mode		= HTTP::_GP('mode', 0);
		if ($mode == 2) {
			Database::get()->insert(
				"INSERT INTO %%NEWS%% (`id`, `user`, `date`, `title`, `text`) VALUES (NULL, :user, :date, :title, :text);",
				[':user' => $USER['username'], ':date' => TIMESTAMP, ':title' => $title, ':text' => $text]
			);
		} else {
			Database::get()->update(
				"UPDATE %%NEWS%% SET `title` = :title, `text` = :text, `date` = :date WHERE `id` = :id LIMIT 1;",
				[':title' => $title, ':text' => $text, ':date' => TIMESTAMP, ':id' => $edit_id]
			);
		}
	} elseif($_POST['action'] == 'delete' && isset($_POST['id'])) {
		Database::get()->delete(
			"DELETE FROM %%NEWS%% WHERE `id` = :id;",
			[':id' => HTTP::_GP('id', 0)]
		);
	}

	$rows = Database::get()->select("SELECT * FROM %%NEWS%% ORDER BY id ASC");

	$NewsList = [];
	foreach ($rows as $u) {
		$NewsList[]	= array(
			'id'		=> $u['id'],
			'title'		=> $u['title'],
			'date'		=> _date($LNG['php_tdformat'], $u['date'], $USER['timezone']),
			'user'		=> $u['user'],
			'confirm'	=> sprintf($LNG['nws_confirm'], $u['title']),
		);
	}

	$template	= new template();


	if($_GET['action'] == 'edit' && isset($_GET['id'])) {
		$News = Database::get()->selectSingle(
			"SELECT id, title, text FROM %%NEWS%% WHERE id = :id;",
			[':id' => HTTP::_GP('id', 0)]
		);
		$template->assign_vars(array(
			'mode'			=> 1,
			'nws_head'		=> sprintf($LNG['nws_head_edit'], $News['title']),
			'news_id'		=> $News['id'],
			'news_title'	=> $News['title'],
			'news_text'		=> $News['text'],
		));
	} elseif($_GET['action'] == 'create') {
		$template->assign_vars(array(
			'mode'			=> 2,
			'nws_head'		=> $LNG['nws_head_create'],
		));
	}

	$template->assign_vars(array(
		'NewsList'		=> $NewsList,
		'button_submit'	=> $LNG['button_submit'],
		'nws_total'		=> sprintf($LNG['nws_total'], $NewsList && count($NewsList)),
		'nws_news'		=> $LNG['nws_news'],
		'nws_id'		=> $LNG['nws_id'],
		'nws_title'		=> $LNG['nws_title'],
		'nws_date'		=> $LNG['nws_date'],
		'nws_from'		=> $LNG['nws_from'],
		'nws_del'		=> $LNG['nws_del'],
		'nws_create'	=> $LNG['nws_create'],
		'nws_content'	=> $LNG['nws_content'],
	));

	$template->show('NewsPage.tpl');
}
