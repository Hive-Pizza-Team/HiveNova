<?php

namespace HiveNova\Page\Login;

use HiveNova\Core\HTTP;

/**
 * Lobby feedback endpoint — disabled. Feedback is in-game only (authenticated).
 */
class ShowFeedbackPage extends AbstractLoginPage
{
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
		global $LNG;

		HTTP::sendHeader('HTTP/1.1 401 Unauthorized');
		$this->sendJSON([
			'ok' => false,
			'error' => 'auth_required',
			'message' => $LNG['fb_error_retry'] ?? 'Could not send feedback. Please try again.',
		]);
	}
}
