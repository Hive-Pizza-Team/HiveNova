<?php

use HiveNova\Core\Config;
use HiveNova\Core\Universe;

/**
 * Snapshot/restore game globals mutated by commander-loop unit tests.
 */
trait RestoreGameGlobals
{
	/** @var mixed */
	private $savedReslist;

	/** @var mixed */
	private $savedResource;

	/** @var mixed */
	private $savedPricelist;

	/** @var mixed */
	private $savedLng;

	/** @var mixed */
	private $savedUser;

	/** @var mixed */
	private $savedPlanet;

	private array $savedServer = [];

	private array $savedSession = [];

	private array $savedRequest = [];

	/** @var list<int> */
	private array $savedUniverses = [];

	protected function snapshotGameGlobals(): void
	{
		$this->savedReslist = $GLOBALS['reslist'] ?? null;
		$this->savedResource = $GLOBALS['resource'] ?? null;
		$this->savedPricelist = $GLOBALS['pricelist'] ?? null;
		$this->savedLng = $GLOBALS['LNG'] ?? null;
		$this->savedUser = $GLOBALS['USER'] ?? null;
		$this->savedPlanet = $GLOBALS['PLANET'] ?? null;
		$this->savedServer = $_SERVER;
		$this->savedSession = $_SESSION ?? [];
		$this->savedRequest = $_REQUEST;
		$ref = new ReflectionProperty(Universe::class, 'availableUniverses');
		$ref->setAccessible(true);
		$saved = $ref->getValue();
		$this->savedUniverses = is_array($saved) ? $saved : [];
	}

	protected function restoreGameGlobals(): void
	{
		if (is_array($this->savedReslist)) {
			$GLOBALS['reslist'] = $this->savedReslist;
		}
		if (is_array($this->savedResource)) {
			$GLOBALS['resource'] = $this->savedResource;
		}
		if (is_array($this->savedPricelist)) {
			$GLOBALS['pricelist'] = $this->savedPricelist;
		}
		if ($this->savedLng === null) {
			unset($GLOBALS['LNG']);
		} else {
			$GLOBALS['LNG'] = $this->savedLng;
		}
		if ($this->savedUser === null) {
			unset($GLOBALS['USER']);
		} else {
			$GLOBALS['USER'] = $this->savedUser;
		}
		if ($this->savedPlanet === null) {
			unset($GLOBALS['PLANET']);
		} else {
			$GLOBALS['PLANET'] = $this->savedPlanet;
		}
		$_SERVER = $this->savedServer;
		$_SESSION = $this->savedSession;
		$_REQUEST = $this->savedRequest;

		$ref = new ReflectionProperty(Universe::class, 'availableUniverses');
		$ref->setAccessible(true);
		$ref->setValue(null, $this->savedUniverses);

		$configRef = new ReflectionProperty(Config::class, 'instances');
		$configRef->setAccessible(true);
		$configRef->setValue(null, []);
	}
}
