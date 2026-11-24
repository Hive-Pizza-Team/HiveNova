<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\Config;
use HiveNova\Core\FeedbackService;
use HiveNova\Core\HTTP;
use HiveNova\Core\Universe;

class ShowFeedbackPage extends AbstractGamePage
{
	public static $requireModule = 0;

	protected $disableEcoSystem = true;

	public function __construct()
	{
		parent::__construct();
	}

	public function show()
	{
		$this->submit();
	}

	public function submit()
	{
		global $USER, $PLANET, $LNG;

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			HTTP::sendHeader('HTTP/1.1 405 Method Not Allowed');
			$this->sendJSON([
				'ok' => false,
				'error' => 'method_not_allowed',
				'message' => $LNG['fb_error_retry'] ?? 'Could not send feedback. Please try again.',
			]);
			return;
		}

		if (!FeedbackService::isSameOriginRequest()) {
			HTTP::sendHeader('HTTP/1.1 403 Forbidden');
			$this->sendJSON([
				'ok' => false,
				'error' => 'cross_origin',
				'message' => $LNG['fb_error_retry'] ?? 'Could not send feedback. Please try again.',
			]);
			return;
		}

		$token = HTTP::_GP('csrf', '');
		if (!FeedbackService::validateCsrfToken($token)) {
			HTTP::sendHeader('HTTP/1.1 403 Forbidden');
			$this->sendJSON([
				'ok' => false,
				'error' => 'invalid_token',
				'message' => $LNG['fb_error_csrf'] ?? 'Security token expired. Reload the page and try again.',
			]);
			return;
		}

		$now = defined('TIMESTAMP') ? (int) TIMESTAMP : time();
		$wait = FeedbackService::rateLimitRemaining(FeedbackService::lastSubmitAtFromSession(), $now);
		if ($wait > 0) {
			HTTP::sendHeader('HTTP/1.1 429 Too Many Requests');
			$this->sendJSON([
				'ok' => false,
				'error' => 'rate_limited',
				'retryAfter' => $wait,
				'message' => sprintf(
					(string) ($LNG['fb_error_rate'] ?? 'Please wait %d seconds before sending more feedback.'),
					$wait
				),
			]);
			return;
		}

		$description = HTTP::_GP('description', '', true);
		$type = HTTP::_GP('type', FeedbackService::TYPE_OTHER);
		$config = Config::get();
		$context = FeedbackService::buildContext([
			'pageUrl' => HTTP::_GP('pageUrl', '', true),
			'universe' => Universe::current(),
			'userId' => (int) ($USER['id'] ?? 0),
			'username' => (string) ($USER['username'] ?? ''),
			'planetId' => (int) ($PLANET['id'] ?? 0),
			'planetName' => (string) ($PLANET['name'] ?? ''),
			'planetCoords' => isset($PLANET['galaxy'], $PLANET['system'], $PLANET['planet'])
				? $PLANET['galaxy'] . ':' . $PLANET['system'] . ':' . $PLANET['planet']
				: '',
			'userAgent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
			'viewport' => HTTP::_GP('viewport', '', true),
			'language' => (string) ($LNG->getLanguage() ?? ''),
			'version' => (string) ($config->VERSION ?? ''),
			'timestamp' => $now,
		]);

		$screenshot = isset($_FILES['screenshot']) && is_array($_FILES['screenshot'])
			? $_FILES['screenshot']
			: null;

		$result = FeedbackService::submit(
			$description,
			$type,
			$context,
			(int) ($USER['id'] ?? 0),
			(string) ($USER['username'] ?? ''),
			$screenshot
		);

		if (empty($result['ok'])) {
			HTTP::sendHeader('HTTP/1.1 400 Bad Request');
			$this->sendJSON([
				'ok' => false,
				'error' => $result['error'] ?? 'submit_failed',
				'message' => $this->errorMessage((string) ($result['error'] ?? 'submit_failed'), $LNG),
			]);
			return;
		}

		FeedbackService::rememberSubmitAt($now);
		FeedbackService::issueCsrfToken();

		$this->sendJSON([
			'ok' => true,
			'reference' => $result['reference'],
			'message' => sprintf(
				(string) ($LNG['fb_success'] ?? 'Thanks! Your report was sent. Reference: %s'),
				$result['reference']
			),
			'csrf' => $_SESSION[FeedbackService::CSRF_SESSION_KEY] ?? '',
		]);
	}

	private function errorMessage(string $code, mixed $LNG): string
	{
		return match ($code) {
			'empty_description' => (string) ($LNG['fb_error_empty'] ?? 'Please enter a description.'),
			'description_too_long' => (string) ($LNG['fb_error_too_long'] ?? 'Description is too long.'),
			'screenshot_too_large' => (string) ($LNG['fb_error_screenshot_size'] ?? 'Screenshot must be 2 MB or smaller.'),
			'screenshot_invalid_type' => (string) ($LNG['fb_error_screenshot_type'] ?? 'Screenshots must be PNG, JPEG, GIF, or WebP.'),
			'screenshot_upload_failed' => (string) ($LNG['fb_error_screenshot_upload'] ?? 'Could not upload the screenshot. Try again without it.'),
			default => (string) ($LNG['fb_error_retry'] ?? 'Could not send feedback. Please try again.'),
		};
	}
}
