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

use HiveNova\Core\Database;
use HiveNova\Core\HTTP;
use HiveNova\Core\Universe;
use HiveNova\Core\Template;


if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) throw new Exception("Permission error!");

function ShowBanPage() 
{
	global $LNG, $USER;

	$db = Database::get();
	$universe = Universe::getEmulated();
	$orderCol = ($_GET['order'] ?? '') === 'id' ? 'id' : 'username';
	$bannedOnly = (($_GET['view'] ?? '') === 'bana');

	$sql = 'SELECT username, id, bana FROM %%USERS%%
		WHERE id != 1 AND authlevel <= :authlevel AND universe = :universe';
	if ($bannedOnly) {
		$sql .= ' AND bana = 1';
	}
	$sql .= ' ORDER BY '.$orderCol.' ASC';

	$userRows = $db->select($sql, [
		':authlevel' => (int) $USER['authlevel'],
		':universe'  => $universe,
	]);

	$UserSelect = array('List' => '', 'ListBan' => '');
	$Users = 0;
	foreach ($userRows as $a) {
		$UserSelect['List'] .= '<option value="'.$a['username'].'">'.$a['username'].'&nbsp;&nbsp;(ID:&nbsp;'.$a['id'].')'.(($a['bana'] == '1') ? $LNG['bo_characters_suus'] : '').'</option>';
		$Users++;
	}

	$orderCol2 = ($_GET['order2'] ?? '') === 'id' ? 'id' : 'username';
	$banRows = $db->select(
		'SELECT username, id FROM %%USERS%% WHERE bana = 1 AND universe = :universe ORDER BY '.$orderCol2.' ASC',
		[':universe' => $universe]
	);

	$Banneds = 0;
	foreach ($banRows as $b) {
		$UserSelect['ListBan'] .= '<option value="'.$b['username'].'">'.$b['username'].'&nbsp;&nbsp;(ID:&nbsp;'.$b['id'].')</option>';
		$Banneds++;
	}

	$template = new Template();
	$template->loadscript('filterlist.js');

	$Name = HTTP::_GP('ban_name', '', true);
	$BANUSER = $db->selectSingle(
		'SELECT b.theme, b.longer, u.id, u.urlaubs_modus, u.banaday
		FROM %%USERS%% AS u
		LEFT JOIN %%BANNED%% AS b ON u.username = b.who
		WHERE u.username = :name AND u.universe = :universe',
		[
			':name'      => $Name,
			':universe' => $universe,
		]
	);

	if(isset($_POST['panel']) && is_array($BANUSER))
	{
		if ($BANUSER['banaday'] <= TIMESTAMP)
		{
			$title			= $LNG['bo_bbb_title_1'];
			$changedate		= $LNG['bo_bbb_title_2'];
			$changedate_advert		= '';
			$reas					= '';
			$timesus				= '';
		}
		else
		{
			$title			= $LNG['bo_bbb_title_3'];
			$changedate		= $LNG['bo_bbb_title_6'];
			$changedate_advert	=	'<td class="c" width="18px"><img src="./styles/resource/images/admin/i.gif" class="tooltip" data-tooltip-content="'.$LNG['bo_bbb_title_4'].'"></td>';
				
			$reas			= $BANUSER['theme'];
			$timesus		=	
				"<tr>
					<th>".$LNG['bo_bbb_title_5']."</th>
					<th height=25 colspan=2>".date($LNG['php_tdformat'], $BANUSER['longer'])."</th>
				</tr>";
		}
		
		
		$vacation	= ($BANUSER['urlaubs_modus'] == 1) ? true : false;
		
		$template->assign_vars(array(	
			'name'				=> $Name,
			'bantitle'			=> $title,
			'changedate'		=> $changedate,
			'reas'				=> $reas,
			'changedate_advert'	=> $changedate_advert,
			'timesus'			=> $timesus,
			'vacation'			=> $vacation,
		));
	} elseif (isset($_POST['bannow']) && is_array($BANUSER) && $BANUSER['id'] != 1) {
		$Name              = HTTP::_GP('ban_name', '' ,true);
		$reas              = HTTP::_GP('why', '' ,true);
		$days              = HTTP::_GP('days', 0);
		$hour              = HTTP::_GP('hour', 0);
		$mins              = HTTP::_GP('mins', 0);
		$secs              = HTTP::_GP('secs', 0);
		$admin             = $USER['username'];
		$mail              = $USER['email'];
		$BanTime           = $days * 86400 + $hour * 3600 + $mins * 60 + $secs;

		if ($BANUSER['longer'] > TIMESTAMP)
			$BanTime          += ($BANUSER['longer'] - TIMESTAMP);
		
		if (isset($_POST['permanent'])) {
			$BannedUntil = 2147483647;
		} else {
			$BannedUntil = ($BanTime + TIMESTAMP) < TIMESTAMP ? TIMESTAMP : TIMESTAMP + $BanTime;
		}
		
		if ($BANUSER['banaday'] > TIMESTAMP)
		{
			$db->update(
				'UPDATE %%BANNED%% SET
				who = :who,
				theme = :theme,
				time = :time,
				longer = :longer,
				author = :author,
				email = :email
				WHERE who2 = :who2 AND universe = :universe',
				[
					':who'       => $Name,
					':theme'     => $reas,
					':time'      => TIMESTAMP,
					':longer'    => $BannedUntil,
					':author'    => $admin,
					':email'     => $mail,
					':who2'      => $Name,
					':universe'  => $universe,
				]
			);
		} else {
			$db->insert(
				'INSERT INTO %%BANNED%% SET
				who = :who,
				theme = :theme,
				time = :time,
				longer = :longer,
				author = :author,
				universe = :universe,
				email = :email',
				[
					':who'       => $Name,
					':theme'     => $reas,
					':time'      => TIMESTAMP,
					':longer'    => $BannedUntil,
					':author'    => $admin,
					':universe'  => $universe,
					':email'     => $mail,
				]
			);
		}

		$db->update(
			'UPDATE %%USERS%% SET
			bana = 1,
			banaday = :banaday,
			urlaubs_modus = :vacation
			WHERE username = :username AND universe = :universe',
			[
				':banaday'   => $BannedUntil,
				':vacation'  => isset($_POST['vacat']) ? 1 : 0,
				':username'  => $Name,
				':universe'  => $universe,
			]
		);

		$template->message($LNG['bo_the_player'].$Name.$LNG['bo_banned'], '?page=bans');
		exit;
	} elseif(isset($_POST['unban_name'])) {
		$Name = HTTP::_GP('unban_name', '', true);
		$db->update(
			'UPDATE %%USERS%% SET bana = 0, banaday = 0 WHERE username = :username AND universe = :universe',
			[
				':username' => $Name,
				':universe'  => $universe,
			]
		);
		$template->message($LNG['bo_the_player2'].$Name.$LNG['bo_unbanned'], '?page=bans');
		exit;
	}

	$template->assign_vars(array(	
		'UserSelect'		=> $UserSelect,
		'usercount'			=> $Users,
		'bancount'			=> $Banneds,
	));
	
	$template->show('BanPage.tpl');
}
