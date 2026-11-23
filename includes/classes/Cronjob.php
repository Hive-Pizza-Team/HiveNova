<?php

namespace HiveNova\Core;

use DirectoryIterator;
use Exception;

require_once __DIR__ . '/../libs/tdcron/class.tdcron.entry.php';
require_once __DIR__ . '/../libs/tdcron/class.tdcron.php';

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

class Cronjob
{
	/** Seconds after which an automatic lock is treated as stale and can be taken over. */
	public const LOCK_STALE_SECONDS = 900;

	/** Value stored by the admin "lock" action; never expires until unlocked. */
	public const LOCK_MANUAL = 'manual';

	function __construct()
	{
		
	}

	/**
	 * Whether an existing lock should be treated as expired.
	 *
	 * Manual admin locks never expire. Numeric unix timestamps expire after
	 * LOCK_STALE_SECONDS. Non-numeric leftover values (legacy md5 tokens) are
	 * treated as stale so a crash cannot leave a job skipped forever.
	 */
	public static function isLockStale(?string $lock, ?int $now = null): bool
	{
		if ($lock === null || $lock === '') {
			return false;
		}
		if ($lock === self::LOCK_MANUAL) {
			return false;
		}
		if (ctype_digit($lock)) {
			$now ??= TIMESTAMP;
			return ($now - (int) $lock) >= self::LOCK_STALE_SECONDS;
		}

		return true;
	}

	public static function isLockAcquirable(?string $lock, ?int $now = null): bool
	{
		return $lock === null || $lock === '' || self::isLockStale($lock, $now);
	}

	/**
	 * SQL fragment matching a free or stale lock. Bind :staleBefore
	 * (TIMESTAMP - LOCK_STALE_SECONDS) and :manualLock (LOCK_MANUAL).
	 */
	private static function lockAcquirableSql(): string
	{
		return '(`lock` IS NULL
			OR (`lock` REGEXP \'^[0-9]+$\' AND CAST(`lock` AS UNSIGNED) <= :staleBefore)
			OR (`lock` NOT REGEXP \'^[0-9]+$\' AND `lock` <> :manualLock))';
	}

	public static function isValidClassName(string $class): bool
	{
		return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class);
	}

	public static function classFilePath(string $class, string $directory = 'includes/classes/cronjob'): string
	{
		return rtrim($directory, '/\\') . '/' . $class . '.php';
	}

	public static function classFileExists(string $class, string $directory = 'includes/classes/cronjob'): bool
	{
		return self::isCronjobClassName($class) && is_file(self::classFilePath($class, $directory));
	}

	public static function isCronjobClassName(string $class): bool
	{
		return self::isValidClassName($class) && str_ends_with($class, 'Cronjob');
	}

	/**
	 * @return list<string>
	 */
	public static function listAvailableClasses(string $directory = 'includes/classes/cronjob'): array
	{
		if (!is_dir($directory)) {
			return [];
		}

		$classes = [];
		$dir = new DirectoryIterator($directory);
		foreach ($dir as $fileinfo) {
			if (!$fileinfo->isFile()) {
				continue;
			}
			$filename = $fileinfo->getFilename();
			if (!str_ends_with($filename, 'Cronjob.php')) {
				continue;
			}
			$class = $fileinfo->getBasename('.php');
			if (!self::isCronjobClassName($class)) {
				continue;
			}
			$classes[] = $class;
		}
		sort($classes);

		return $classes;
	}
	
	static function execute($cronjobID)
	{
		$lockToken	= (string) TIMESTAMP;
		$staleBefore	= TIMESTAMP - self::LOCK_STALE_SECONDS;

		$db	= Database::get();

		$sql = 'SELECT class, `lock` FROM %%CRONJOBS%% WHERE isActive = :isActive AND cronjobID = :cronjobId;';

		$cronjobRow	= $db->selectSingle($sql, array(
			':isActive'		=> 1,
			':cronjobId'	=> $cronjobID
		));

		$cronjobClassName	= is_array($cronjobRow) ? ($cronjobRow['class'] ?? '') : '';

		if(empty($cronjobClassName))
		{
			throw new Exception(sprintf("Unknown cronjob %s or cronjob is deactive!", $cronjobID));
		}

		$currentLock	= is_array($cronjobRow) ? ($cronjobRow['lock'] ?? null) : null;
		if(!self::isLockAcquirable($currentLock === null ? null : (string) $currentLock))
		{
			throw new Exception(sprintf("Cronjob %s is already locked!", $cronjobID));
		}
		
		$sql = 'UPDATE %%CRONJOBS%% SET `lock` = :lock WHERE cronjobID = :cronjobId AND ' . self::lockAcquirableSql() . ';';

		$db->update($sql, array(
			':lock'			=> $lockToken,
			':cronjobId'	=> $cronjobID,
			':staleBefore'	=> $staleBefore,
			':manualLock'	=> self::LOCK_MANUAL,
		));

		if ($db->rowCount() < 1)
		{
			throw new Exception(sprintf("Cronjob %s is already locked!", $cronjobID));
		}
		
		try {
			/** @var \HiveNova\Cronjob\CronjobTask $cronjobObj */
			$cronjobObj			= new $cronjobClassName;
			$cronjobObj->run();

			self::reCalculateCronjobs($cronjobID);

			$sql = 'INSERT INTO %%CRONJOBS_LOG%% SET `cronjobId` = :cronjobId,
			`executionTime` = :executionTime, `lockToken` = :lockToken';

			$db->insert($sql, array(
				':cronjobId'		=> $cronjobID,
				':executionTime'	=> Database::formatDate(TIMESTAMP),
				':lockToken'		=> $lockToken
			));
		} finally {
			$sql = 'UPDATE %%CRONJOBS%% SET `lock` = NULL WHERE cronjobID = :cronjobId;';

			$db->update($sql, array(
				':cronjobId'	=> $cronjobID
			));
		}
	}
	
	static function getNeedTodoExecutedJobs()
	{
		$sql			= 'SELECT cronjobID, `lock`
		FROM %%CRONJOBS%%
		WHERE isActive = :isActive AND nextTime < :time;';

		$cronjobResult	= Database::get()->select($sql, array(
			':isActive'	=> 1,
			':time'		=> TIMESTAMP
 		));

		$cronjobList	= array();

		foreach($cronjobResult as $cronjobRow)
		{
			$lock	= $cronjobRow['lock'] ?? null;
			if(self::isLockAcquirable($lock === null ? null : (string) $lock))
			{
				$cronjobList[]	= $cronjobRow['cronjobID'];
			}
		}
		
		return $cronjobList;
	}

	static function getLastExecutionTime($cronjobName)
	{
		require_once 'includes/libs/tdcron/class.tdcron.php';
		require_once 'includes/libs/tdcron/class.tdcron.entry.php';

		$sql		= 'SELECT MAX(executionTime) as executionTime FROM %%CRONJOBS_LOG%% INNER JOIN %%CRONJOBS%% USING(cronjobId) WHERE name = :cronjobName;';
		$lastTime	= Database::get()->selectSingle($sql, array(
			':cronjobName' => $cronjobName
		), 'executionTime');

		if(empty($lastTime))
		{
			return false;
		}

		return strtotime((string) $lastTime);
	}
	
	static function reCalculateCronjobs($cronjobID = NULL)
	{
		require_once 'includes/libs/tdcron/class.tdcron.php';
		require_once 'includes/libs/tdcron/class.tdcron.entry.php';

		$db	= Database::get();

		if(!empty($cronjobID))
		{
			$sql			= 'SELECT cronjobID, min, hours, dom, month, dow FROM %%CRONJOBS%% WHERE cronjobID = :cronjobId;';
			$cronjobResult	= $db->select($sql, array(
				':cronjobId' => $cronjobID
			));
		}
		else
		{
			$sql			= 'SELECT cronjobID, min, hours, dom, month, dow FROM %%CRONJOBS%%;';
			$cronjobResult	= $db->select($sql);
		}

		$sql = 'UPDATE %%CRONJOBS%% SET nextTime = :nextTime WHERE cronjobID = :cronjobId;';

		foreach($cronjobResult as $cronjobRow)
		{
			$cronTabString	= implode(' ', array($cronjobRow['min'], $cronjobRow['hours'], $cronjobRow['dom'], $cronjobRow['month'], $cronjobRow['dow']));
			$nextTime		= \tdCron::getNextOccurrence($cronTabString, TIMESTAMP + 60);

			$db->update($sql, array(
				':nextTime'		=> $nextTime,
				':cronjobId'	=> $cronjobRow['cronjobID'],
			));
		}
	}
}
