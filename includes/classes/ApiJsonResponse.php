<?php

namespace HiveNova\Core;

class ApiJsonResponse
{
	public const ENCODE_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $meta
	 */
	public static function encodeSuccess(array $data, array $meta = []): string
	{
		return json_encode([
			'ok' => true,
			'data' => $data,
			'meta' => $meta,
		], self::ENCODE_FLAGS);
	}

	public static function encodeError(string $error, string $message = ''): string
	{
		$payload = [
			'ok' => false,
			'error' => $error,
		];
		if ($message !== '') {
			$payload['message'] = $message;
		}

		return json_encode($payload, self::ENCODE_FLAGS);
	}

	public static function sendHeaders(int $status): void
	{
		if (!headers_sent()) {
			http_response_code($status);
			header('Content-Type: application/json');
			header('Cache-Control: no-store');
		}
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $meta
	 */
	public static function sendSuccess(array $data, array $meta = [], int $status = 200): never
	{
		self::sendHeaders($status);
		echo self::encodeSuccess($data, $meta);
		exit;
	}

	public static function sendError(string $error, int $status, string $message = ''): never
	{
		self::sendHeaders($status);
		echo self::encodeError($error, $message);
		exit;
	}

	public static function isApiMode(): bool
	{
		return defined('MODE') && MODE === 'API';
	}
}
