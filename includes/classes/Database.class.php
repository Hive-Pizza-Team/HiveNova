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

class Database implements DatabaseInterface
{
	protected $dbHandle = NULL;
	protected $dbTableNames = array();
	protected $lastInsertId = false;
	protected $rowCount = false;
	protected $queryCounter = 0;
	protected static DatabaseInterface|null $instance = NULL;


	public static function get(): DatabaseInterface
	{
		if (!isset(self::$instance))
			self::$instance = new self();

		return self::$instance;
	}

	public static function setInstance(DatabaseInterface $db): void
	{
		self::$instance = $db;
	}

	public function getDbTableNames()
	{
		return $this->dbTableNames;
	}

	private function __clone()
	{

	}

	protected function __construct()
	{
		$database = array();
		require 'includes/config.php';
		//Connect
		$db = new PDO("mysql:host=".$database['host'].";port=".$database['port'].";dbname=".$database['databasename'], $database['user'], $database['userpw'], array(
		    PDO::MYSQL_ATTR_INIT_COMMAND => "SET CHARACTER SET utf8mb4, NAMES utf8mb4, sql_mode = 'STRICT_ALL_TABLES'"
		));
		//error behaviour
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
		// $db->query("set character set utf8");
		// $db->query("set names utf8");
		// $db->query("SET sql_mode = 'STRICT_ALL_TABLES'");
		$this->dbHandle = $db;

		$dbTableNames = array();

		include 'includes/dbtables.php';

		foreach($dbTableNames as $key => $name)
		{
			$this->dbTableNames['keys'][]	= '%%'.$key.'%%';
			$this->dbTableNames['names'][]	= $name;
		}
	}

	public function disconnect()
	{
		$this->dbHandle = NULL;
	}

	public function getHandle()
	{
		return $this->dbHandle;
	}

	public function lastInsertId()
	{
		return $this->lastInsertId;
	}

	public function rowCount()
	{
		return $this->rowCount;
	}

	protected function _query($qry, array $params, $type)
	{
		if (in_array($type, array("insert", "select", "update", "delete", "replace")) === false)
		{
			throw new Exception("Unsupported Query Type");
		}

		$this->lastInsertId = false;
		$this->rowCount = false;

		$qry	= str_replace($this->dbTableNames['keys'], $this->dbTableNames['names'], $qry);

		/** @var $stmt PDOStatement */
		$stmt	= $this->dbHandle->prepare($qry);

		if (isset($params[':limit']) || isset($params[':offset']))
		{
			foreach($params as $param => $value)
			{
				if($param == ':limit' || $param == ':offset')
				{
					$stmt->bindValue($param, (int) $value, PDO::PARAM_INT);
				}
				else
				{
					$stmt->bindValue($param, $value, PDO::PARAM_STR);
				}
			}
		}

		try {
			$success = (count($params) !== 0 && !isset($params[':limit']) && !isset($params[':offset'])) ? $stmt->execute($params) : $stmt->execute();
		}
		catch (PDOException $e) {
			error_log('DB error: ' . $e->getMessage() . ' | Query: ' . str_replace(array_keys($params), array_values($params), $qry));
			throw new Exception('A database error occurred. Please try again later.');
		}

		$this->queryCounter++;

		if (!$success)
			return false;

		if ($type === "insert")
			$this->lastInsertId = $this->dbHandle->lastInsertId();
		$this->rowCount = $stmt->rowCount();

		return ($type === "select") ? $stmt : true;
	}

	protected function getQueryType($qry)
	{
		if(!preg_match('!^(\S+)!', (string) $qry, $match))
        {
            throw new Exception("Invalid query $qry!");
        }

		if(!isset($match[1]))
        {
            throw new Exception("Invalid query $qry!");
        }

		return strtolower($match[1]);
	}

	public function delete($qry, array $params = array())
	{
		if (($type = $this->getQueryType($qry)) !== "delete")
			throw new Exception("Incorrect Delete Query");

		return $this->_query($qry, $params, $type);
	}

	public function replace($qry, array $params = array())
	{
		if (($type = $this->getQueryType($qry)) !== "replace")
			throw new Exception("Incorrect Replace Query");

		return $this->_query($qry, $params, $type);
	}

	public function update($qry, array $params = array())
	{
		if (($type = $this->getQueryType($qry)) !== "update")
			throw new Exception("Incorrect Update Query");

		return $this->_query($qry, $params, $type);
	}

	public function insert($qry, array $params = array())
	{
		if (($type = $this->getQueryType($qry)) !== "insert")
			throw new Exception("Incorrect Insert Query");

		return $this->_query($qry, $params, $type);
	}

	public function select($qry, array $params = array())
	{
		if (($type = $this->getQueryType($qry)) !== "select")
			throw new Exception("Incorrect Select Query");

		$stmt = $this->_query($qry, $params, $type);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function selectSingle($qry, array $params = array(), $field = false)
	{
		if (($type = $this->getQueryType($qry)) !== "select")
			throw new Exception("Incorrect Select Query");

		$stmt = $this->_query($qry, $params, $type);
		$res = $stmt->fetch(PDO::FETCH_ASSOC);

		if(PHP_VERSION_ID <= 70400) {
			return ($field === false || is_null($res)) ? $res : $res[$field];
		} else {
			return ($field === false || (empty($res))) ? $res : $res[$field];
		}
	}

	/**
	 * Execute a raw SQL string via PDO::exec().
	 *
	 * @internal Only use for fully static, trusted SQL strings (migrations,
	 *           schema changes, OPTIMIZE TABLE). Never pass user-supplied data.
	 */
	public function query($qry)
	{
		$this->lastInsertId = false;
		$this->rowCount = false;
		$this->rowCount = $this->dbHandle->exec($qry);
		$this->queryCounter++;
	}

	/**
	 * Execute a raw SQL string via PDO::query() with table-name substitution.
	 *
	 * @internal Only use for fully static, trusted SQL strings (SHOW STATUS,
	 *           SHOW COLUMNS, stat-builder INSERTs). Never pass user-supplied data.
	 */
	public function nativeQuery($qry)
	{
		$this->lastInsertId = false;
		$this->rowCount = false;

		$qry	= str_replace($this->dbTableNames['keys'], $this->dbTableNames['names'], $qry);

		/** @var $stmt PDOStatement */
		$stmt	= $this->dbHandle->query($qry);

		$this->rowCount = $stmt->rowCount();

		$this->queryCounter++;
		return in_array($this->getQueryType($qry), array('select', 'show')) ? $stmt->fetchAll(PDO::FETCH_ASSOC) : true;
	}

	public function getQueryCounter()
	{
		return $this->queryCounter;
	}

	static public function formatDate($time)
	{
		return date('Y-m-d H:i:s', $time);
	}

	public function quote($str)
	{
		return $this->dbHandle->quote($str);
	}

	public function beginTransaction(): void
	{
		$this->dbHandle->beginTransaction();
	}

	public function commit(): void
	{
		$this->dbHandle->commit();
	}

	public function rollback(): void
	{
		if ($this->dbHandle->inTransaction()) {
			$this->dbHandle->rollBack();
		}
	}
}
