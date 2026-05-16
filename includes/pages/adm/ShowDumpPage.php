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

use HiveNova\Core\HTTP;
use HiveNova\Core\SQLDumper;
use HiveNova\Core\Template;


if ($USER['authlevel'] == AUTH_USR)
{
	throw new PagePermissionException("Permission error!");
}

/**
 * @return list<string>
 */
function dumpPageGetTableNames(): array
{
	$prefixCounts	= strlen((string) DB_PREFIX);
	$sqlTables		= array();
	$sqlTableRaw	= $GLOBALS['DATABASE']->query("SHOW TABLE STATUS FROM `".DB_NAME."`;");

	while($table = $GLOBALS['DATABASE']->fetchArray($sqlTableRaw))
	{
		if(DB_PREFIX == substr((string) $table['Name'], 0, $prefixCounts) || $table['Name'] === 'transactions')
		{
			$sqlTables[]	= $table['Name'];
		}
	}

	return $sqlTables;
}

/**
 * @return list<array{file: string, size: int, mtime: int}>
 */
function dumpPageListBackupFiles(): array
{
	$backupDir	= ROOT_PATH . 'includes/backups/';
	$files		= array();

	if(!is_dir($backupDir))
	{
		return $files;
	}

	foreach(scandir($backupDir) as $entry)
	{
		if($entry === '.' || $entry === '..')
		{
			continue;
		}

		if(!str_starts_with($entry, '2MoonsBackup') || !str_ends_with($entry, '.sql'))
		{
			continue;
		}

		$fullPath	= $backupDir . $entry;
		if(!is_file($fullPath) || !is_readable($fullPath))
		{
			continue;
		}

		$size		= filesize($fullPath);
		$mtime		= filemtime($fullPath);
		$files[]	= array(
			'file'	=> $entry,
			'size'	=> $size,
			'mtime'	=> $mtime,
			'label'	=> $entry . ' (' . number_format($size) . ' B, ' . date('Y-m-d H:i', $mtime) . ')',
		);
	}

	usort($files, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

	return $files;
}

function dumpPageResolveBackupPath(string $fileName): string
{
	$fileName	= basename($fileName);

	if($fileName === '' || !str_starts_with($fileName, '2MoonsBackup') || !str_ends_with($fileName, '.sql'))
	{
		throw new Exception('Invalid backup file name.');
	}

	$backupDir	= ROOT_PATH . 'includes/backups/';
	$fullPath	= $backupDir . $fileName;

	if(!is_file($fullPath) || !is_readable($fullPath))
	{
		throw new Exception('Backup file not found or not readable.');
	}

	$realDir	= realpath($backupDir);
	$realFile	= realpath($fullPath);

	if($realDir === false || $realFile === false || !str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR))
	{
		throw new Exception('Backup file path is not allowed.');
	}

	return $realFile;
}

/**
 * @return string Relative path for display (includes/backups/…)
 */
function dumpPageCreateFullBackup(string $nameSuffix = ''): string
{
	$dbTables	= dumpPageGetTableNames();

	if($dbTables === [])
	{
		throw new Exception('No tables matched for backup.');
	}

	$suffix		= $nameSuffix !== '' ? '_' . $nameSuffix : '';
	$fileName	= '2MoonsBackup' . $suffix . '_' . date('d_m_Y_H_i_s', TIMESTAMP) . '.sql';
	$relPath	= 'includes/backups/' . $fileName;
	$filePath	= ROOT_PATH . $relPath;

	$dir	= dirname($filePath);
	if(!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir))
	{
		throw new Exception('Cannot create backup directory: ' . $dir);
	}

	$dump	= new SQLDumper();
	$dump->dumpTablesToFile($dbTables, $filePath);

	return $relPath;
}

function ShowDumpPage()
{
	global $LNG, $USER;
	if(!isset($_POST['action'])) { $_POST['action'] = ''; }
	switch($_POST['action'])
	{
		case 'dump':
			$dbTables	= HTTP::_GP('dbtables', array());
			if(empty($dbTables)) {
				$template	= new Template();
				$template->message($LNG['du_not_tables_selected']);
				exit;
			}
			
			$fileName	= '2MoonsBackup_'.date('d_m_Y_H_i_s', TIMESTAMP).'.sql';
			$filePath	= ROOT_PATH . 'includes/backups/'.$fileName;
			$dir		= dirname($filePath);
			if(!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir))
			{
				$template	= new Template();
				$template->message($LNG['du_backup_dir_error']);
				exit;
			}
		
			$dump	= new SQLDumper;
			$dump->dumpTablesToFile($dbTables, $filePath);
			
			$template	= new Template();
			$template->message(sprintf($LNG['du_success'], 'includes/backups/'.$fileName));
		break;
		case 'restore':
			if($USER['authlevel'] < AUTH_ADM)
			{
				throw new PagePermissionException("Permission error!");
			}

			$backupFile		= HTTP::_GP('backup_file', '');
			$confirmRestore	= HTTP::_GP('confirm_restore', 0);
			$backupBefore	= HTTP::_GP('backup_before', 1);

			$template	= new Template();

			if($backupFile === '')
			{
				$template->message($LNG['du_restore_not_selected']);
				exit;
			}

			if(!$confirmRestore)
			{
				$template->message($LNG['du_restore_not_confirmed']);
				exit;
			}

			try {
				$filePath		= dumpPageResolveBackupPath($backupFile);
				$safetyBackup	= null;

				if($backupBefore)
				{
					$safetyBackup	= dumpPageCreateFullBackup('pre_restore');
				}

				$dump	= new SQLDumper();
				$dump->restoreDatabase($filePath);
				ClearCache();

				$message	= sprintf($LNG['du_restore_success'], $backupFile);
				if($safetyBackup !== null)
				{
					$message	.= '<br><br>' . sprintf($LNG['du_restore_safety_backup'], $safetyBackup);
				}

				$template->message($message);
			}
			catch (Exception $e) {
				$template->message(sprintf($LNG['du_restore_error'], $e->getMessage()));
			}
		break;
		default:
			$dumpData		= array();
			$dumpData['perRequest']		= 100;
			$dumpData['sqlTables']		= dumpPageGetTableNames();
			$dumpData['backupFiles']	= dumpPageListBackupFiles();
			$dumpData['canRestore']		= $USER['authlevel'] >= AUTH_ADM;

			$template	= new Template();

			$template->assign_vars(array(	
				'dumpData'	=> $dumpData,
			));
			
			$template->show('DumpPage.tpl');
		break;
	}
}
