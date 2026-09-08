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

	/** @var array<string, list<string>> */
	private const MUTATE_ACTIONS = [
		'overview' => ['rename'],
	];

	public static function sanitizeResource(string $raw): string
	{
		return preg_replace('/[^a-z0-9]/', '', strtolower($raw)) ?? '';
	}

	public static function sanitizeAction(string $raw): string
	{
		return preg_replace('/[^a-z]/', '', strtolower($raw)) ?? '';
	}

	public static function tickClass(string $resource, string $action, string $method): ApiTickClass
	{
		if (self::isMutateAction($resource, strtolower($action))) {
			return ApiTickClass::Mutate;
		}

		return self::RESOURCES[$resource] ?? ApiTickClass::Read;
	}

	public static function isKnown(string $resource): bool
	{
		return isset(self::RESOURCES[$resource]);
	}

	public static function isKnownAction(string $resource, string $action): bool
	{
		if ($action === '' || $action === 'show') {
			return isset(self::RESOURCES[$resource]);
		}

		return self::isMutateAction($resource, $action);
	}

	public static function isMutateAction(string $resource, string $action): bool
	{
		return in_array($action, self::MUTATE_ACTIONS[$resource] ?? [], true);
	}

	public static function seasonPageAlias(string $resource): string
	{
		return match ($resource) {
			'bootstrap', 'overview', 'alerts', 'events', 'i18n' => 'overview',
			default => $resource,
		};
	}
}
