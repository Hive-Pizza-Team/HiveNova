<?php

namespace HiveNova\Core;

class ApiRouteTable
{
	/** @var array<string, ApiTickClass> */
	private const RESOURCES = [
		'bootstrap' => ApiTickClass::Read,
		'overview' => ApiTickClass::Read,
		'i18n' => ApiTickClass::Poll,
		'alerts' => ApiTickClass::Poll,
		'events' => ApiTickClass::Poll,
	];

	/** @var array<string, ApiTickClass> */
	private const ACTIONS = [
		'rename' => ApiTickClass::Mutate,
		'delete' => ApiTickClass::Mutate,
	];

	public static function tickClass(string $resource, string $action, string $method): ApiTickClass
	{
		$action = strtolower($action);
		$method = strtoupper($method);

		if (isset(self::ACTIONS[$action])) {
			return self::ACTIONS[$action];
		}

		if ($method === 'POST' && $resource === 'overview') {
			return ApiTickClass::Mutate;
		}

		return self::RESOURCES[$resource] ?? ApiTickClass::Read;
	}

	public static function isKnown(string $resource): bool
	{
		return isset(self::RESOURCES[$resource]);
	}

	public static function seasonPageAlias(string $resource): string
	{
		return match ($resource) {
			'bootstrap', 'overview', 'alerts', 'events', 'i18n' => 'overview',
			default => $resource,
		};
	}
}
