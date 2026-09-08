<?php

/**
 * Router for PHP's built-in server: SPA fallback under /react, otherwise default file handling.
 *
 * Usage: php -S localhost:8000 router.php
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($uri) ? $uri : '/';

if (preg_match('#^/react(?:/|$)#', $uri) === 1) {
	$file = __DIR__ . $uri;
	if (is_file($file)) {
		return false;
	}

	$index = __DIR__ . '/react/index.html';
	if (is_file($index)) {
		header('Content-Type: text/html; charset=UTF-8');
		header('Cache-Control: no-cache');
		readfile($index);
		return true;
	}

	http_response_code(404);
	header('Content-Type: text/plain; charset=UTF-8');
	echo "React UI is not built. From frontend/ run: npm install && npm run build\n";
	return true;
}

return false;
