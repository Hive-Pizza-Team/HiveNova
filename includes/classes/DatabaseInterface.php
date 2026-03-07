<?php

interface DatabaseInterface
{
	public function select($qry, array $params = array());
	public function selectSingle($qry, array $params = array(), $field = false);
	public function insert($qry, array $params = array());
	public function update($qry, array $params = array());
	public function delete($qry, array $params = array());
	public function replace($qry, array $params = array());
	public function query($qry);
	public function nativeQuery($qry);
	public function lastInsertId();
	public function rowCount();
	public function getQueryCounter();
	public function quote($str);
	public function disconnect();
	public function beginTransaction(): void;
	public function commit(): void;
	public function rollback(): void;
}
