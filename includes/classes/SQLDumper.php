<?php

namespace HiveNova\Core;

use HiveNova\Core\Database;
use HiveNova\Core\Config;
use Exception;

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

class SQLDumper
{
	/**
	 * @param list<mixed> $requested
	 * @param list<string> $allowed
	 * @return list<string>
	 */
	public static function filterAllowedTables(array $requested, array $allowed): array
	{
		$allow = [];
		foreach ($allowed as $name) {
			$name = (string) $name;
			if ($name !== '' && preg_match('/^[A-Za-z0-9_]+$/', $name) === 1) {
				$allow[$name] = true;
			}
		}

		$out = [];
		foreach ($requested as $name) {
			$name = (string) $name;
			if (isset($allow[$name])) {
				$out[] = $name;
			}
		}

		return $out;
	}

	public static function quoteIdentifier(string $name): string
	{
		if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
			throw new Exception('Invalid SQL identifier.');
		}

		return '`' . $name . '`';
	}

	/**
	 * Build a MySQL/MariaDB option-file body for [client] credentials.
	 * Avoids MYSQL_PWD (discouraged) and CLI --password (process-list exposure),
	 * which also trigger MariaDB "passwordless login" SSL warnings on stderr.
	 *
	 * @param array{host?:string,port?:int|string,user?:string,userpw?:string} $database
	 */
	public static function formatClientDefaults(array $database): string
	{
		$host = (string) ($database['host'] ?? '127.0.0.1');
		$port = (int) ($database['port'] ?? 3306);
		$user = (string) ($database['user'] ?? '');
		$password = (string) ($database['userpw'] ?? '');

		return "[client]\n"
			. 'host=' . self::quoteOptionValue($host) . "\n"
			. 'port=' . $port . "\n"
			. 'user=' . self::quoteOptionValue($user) . "\n"
			. 'password=' . self::quoteOptionValue($password) . "\n";
	}

	private static function quoteOptionValue(string $value): string
	{
		return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
	}

	public function dumpTablesToFile($dbTables, $filePath)
	{
		if($this->canNative('mysqldump'))
		{
			return $this->nativeDumpToFile($dbTables, $filePath);
		}
		else
		{
			return $this->softwareDumpToFile($dbTables, $filePath);
		}
	}
	
	private function setTimelimit()
	{
		@set_time_limit(600); // 10 Minutes
	}
		
	protected function canNative($command)
	{
		if (!function_exists('proc_open') || !function_exists('shell_exec') || !function_exists('escapeshellarg')) {
			return false;
		}

		$path = shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null');

		return is_string($path) && trim($path) !== '';
	}

	/**
	 * @param array{host?:string,port?:int|string,user?:string,userpw?:string,databasename?:string} $database
	 */
	private function createClientDefaultsFile(array $database): string
	{
		$path = tempnam(sys_get_temp_dir(), 'hn-my-');
		if ($path === false) {
			throw new Exception('Unable to create temporary MySQL defaults file.');
		}

		if (file_put_contents($path, self::formatClientDefaults($database)) === false) {
			@unlink($path);
			throw new Exception('Unable to write temporary MySQL defaults file.');
		}

		@chmod($path, 0600);

		return $path;
	}

	/**
	 * @param list<string> $arguments Shell-escaped argument tokens (not including the binary)
	 * @param array{0?:array{0:string,1:string,2?:string},1?:array{0:string,1:string,2?:string},2?:array{0:string,1:string,2?:string}}|null $descriptorSpec
	 */
	protected function runNativeClient(string $binary, array $database, array $arguments, ?array $descriptorSpec = null): void
	{
		$defaultsFile = $this->createClientDefaultsFile($database);
		try {
			$command = escapeshellarg($binary)
				. ' --defaults-extra-file=' . escapeshellarg($defaultsFile)
				. (empty($arguments) ? '' : ' ' . implode(' ', $arguments));

			if ($descriptorSpec === null) {
				$descriptorSpec = [
					0 => ['pipe', 'r'],
					1 => ['pipe', 'w'],
					2 => ['pipe', 'w'],
				];
			}

			$process = proc_open($command, $descriptorSpec, $pipes);
			if (!is_resource($process)) {
				throw new Exception('Unable to start ' . $binary);
			}

			if (isset($pipes[0]) && is_resource($pipes[0])) {
				fclose($pipes[0]);
			}

			$stdout = '';
			if (isset($pipes[1]) && is_resource($pipes[1])) {
				$stdout = stream_get_contents($pipes[1]) ?: '';
				fclose($pipes[1]);
			}

			$stderr = '';
			if (isset($pipes[2]) && is_resource($pipes[2])) {
				$stderr = stream_get_contents($pipes[2]) ?: '';
				fclose($pipes[2]);
			}

			$exitCode = proc_close($process);
			if ($exitCode !== 0) {
				$message = trim($stderr !== '' ? $stderr : $stdout);
				throw new Exception($message !== '' ? $message : $binary . ' failed with exit code ' . $exitCode);
			}
		} finally {
			if (is_file($defaultsFile)) {
				@unlink($defaultsFile);
			}
		}
	}
	
	private function nativeDumpToFile($dbTables, $filePath)
	{
		$database	= array();
		require 'includes/config.php';

		$dbTables	= array_map(static fn($table) => escapeshellarg((string) $table), $dbTables);
		$arguments	= array_merge(
			[
				'--no-tablespaces',
				'--no-create-db',
				'--order-by-primary',
				'--add-drop-table',
				'--comments',
				'--complete-insert',
				'--hex-blob',
				escapeshellarg((string) $database['databasename']),
			],
			$dbTables
		);

		$this->runNativeClient('mysqldump', $database, $arguments, [
			0 => ['pipe', 'r'],
			1 => ['file', $filePath, 'w'],
			2 => ['pipe', 'w'],
		]);

		return null;
	}
	
	private function softwareDumpToFile($dbTables, $filePath)
	{
		$this->setTimelimit();

		$db	= Database::get();
		$database	= array();
		require 'includes/config.php';
		$integerTypes	= array('tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'float', 'double', 'real');
		$gameVersion	= Config::get()->VERSION;
		$fp	= fopen($filePath, 'w');
		fwrite($fp, "-- MySQL dump | 2Moons dumper v{$gameVersion}
--
-- Host: {$database['host']}    Database: {$database['databasename']}
-- ------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

");

		foreach($dbTables as $dbTable)
		{
			$quotedTable	= self::quoteIdentifier((string) $dbTable);
			$numColumns	= array();
			$firstRow	= true;

			fwrite($fp, "--\n-- Table structure for table {$quotedTable}\n--\n\nDROP TABLE IF EXISTS {$quotedTable};
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;\n\n");

			$createTable	= $db->nativeQuery("SHOW CREATE TABLE ".$quotedTable);
            $createTableSql = isset($createTable['Create Table']) ?
                $createTable['Create Table'] : (
                #old mysql clients
                isset($createTable[0]['Create Table']) ?
                    $createTable[0]['Create Table'] :
                    false
            );

            if($createTableSql === false) {
                throw new Exception("Error after executing SHOW CREATE TABLE ".$dbTable."! Can't find key 'Create Table' in the results. Available data: \n\n".print_r($createTable, true));
            }

			fwrite($fp, $createTableSql.';');
			fwrite($fp, "\n\n/*!40101 SET character_set_client = @saved_cs_client */;");

			$sql = "SELECT COUNT(*) as state FROM ".$quotedTable.";";

			$count	= $db->nativeQuery($sql);
			if($count[0]['state'] == 0)
			{
				fwrite($fp, "\n\n--\n-- No data for table {$quotedTable}\n--\n\n");
				continue;
			}

			fwrite($fp, "
			
--
-- Dumping data for table {$quotedTable}
--

LOCK TABLES {$quotedTable} WRITE;
/*!40000 ALTER TABLE `{$dbTable}` DISABLE KEYS */;

");
			$columnsData	= $db->nativeQuery("SHOW COLUMNS FROM ".$quotedTable);
			$columnNames	= array();
			foreach($columnsData as $columnData)
			{
				$columnNames[]	= $columnData['Field'];
				foreach($integerTypes as $type)
				{
					if(str_contains((string) $columnData['Type'], $type.'('))
					{
						$numColumns[]	= $columnData['Field'];
						break;
					}
				}
			}
			
			$insertInto	= "INSERT INTO ".$quotedTable." (`".implode("`, `", $columnNames)."`) VALUES\r\n";
			
			fwrite($fp, $insertInto);
			$i = 0;
			$tableData	= $db->select("SELECT * FROM ".$quotedTable);
			foreach($tableData as $tableRow)
			{
				$rowData = array();
				$i++;
				if(($i % 50) === 0)
				{
					$firstRow	= true;
					fwrite($fp, ";\r\n");
					fwrite($fp, $insertInto);
				}
				
				if(!$firstRow)
				{
					fwrite($fp, ",\r\n");
				}
				else
				{
					$firstRow = false;
				}
				
				foreach($tableRow as $colum => $value)
				{
					if(in_array($colum, $numColumns))
					{
						$rowData[]	= $value === NULL ? 'NULL' : $value;
					}
					else
					{
						$rowData[]	= $value === NULL ? 'NULL' : $db->quote($value);
					}
				}
				fwrite($fp, "(".implode(", ",$rowData).")");
			}
			fwrite($fp, ";
			
/*!40000 ALTER TABLE `{$dbTable}` ENABLE KEYS */;
UNLOCK TABLES;

");
		}
		fwrite($fp, "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on ".date("Y-d-m H:i:s"));
		fclose($fp);

		return filesize($filePath) !== 0;
	}
	
	public function restoreDatabase($filePath)
	{
		$this->setTimelimit();

		if(!is_file($filePath) || !is_readable($filePath))
		{
			throw new Exception('Backup file not found or not readable.');
		}

		if($this->canNative('mysql'))
		{
			$database	= array();
			require 'includes/config.php';

			$this->runNativeClient('mysql', $database, [
				escapeshellarg((string) $database['databasename']),
			], [
				0 => ['file', $filePath, 'r'],
				1 => ['pipe', 'w'],
				2 => ['pipe', 'w'],
			]);
		}
		else
		{
			$sql		= file_get_contents($filePath);
			$delimiter	= str_contains($sql, ";\r\n") ? ";\r\n" : ";\n";
			$backupQuery	= explode($delimiter, $sql);
			foreach($backupQuery as $query)
			{
				$query	= trim($query);
				if($query === '')
				{
					continue;
				}
				Database::get()->nativeQuery($query);
			}
		}
	}
}
