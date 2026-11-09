<?php

declare(strict_types=1);

use HiveNova\Core\FeedbackService;
use PHPUnit\Framework\TestCase;

class FeedbackServiceTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		if (session_status() !== PHP_SESSION_ACTIVE) {
			@session_start();
		}
		$_SESSION = [];
	}

	public function testSanitizeUrlStripsTokensAndPasswords(): void
	{
		$url = 'https://moon.hive.pizza/game.php?page=overview&token=secret&password=hunter2&mode=show';
		$clean = FeedbackService::sanitizeUrl($url);

		$this->assertStringContainsString('page=overview', $clean);
		$this->assertStringContainsString('mode=show', $clean);
		$this->assertStringNotContainsString('token=', $clean);
		$this->assertStringNotContainsString('password=', $clean);
		$this->assertStringNotContainsString('secret', $clean);
		$this->assertStringNotContainsString('hunter2', $clean);
	}

	public function testSanitizeUrlDropsFragmentAndSensitiveSid(): void
	{
		$clean = FeedbackService::sanitizeUrl('/index.php?page=login&sid=abc123#access_token=xyz');
		$this->assertSame('/index.php?page=login', $clean);
	}

	public function testNormalizeTypeDefaultsUnknownToOther(): void
	{
		$this->assertSame(FeedbackService::TYPE_BUG, FeedbackService::normalizeType('BUG'));
		$this->assertSame(FeedbackService::TYPE_IDEA, FeedbackService::normalizeType('idea'));
		$this->assertSame(FeedbackService::TYPE_OTHER, FeedbackService::normalizeType(''));
		$this->assertSame(FeedbackService::TYPE_OTHER, FeedbackService::normalizeType('spam'));
	}

	public function testBuildContextFormatsAccountAndPlanet(): void
	{
		$ctx = FeedbackService::buildContext([
			'pageUrl' => 'game.php?page=buildings&token=nope',
			'universe' => 1,
			'userId' => 42,
			'username' => 'Tor',
			'planetId' => 9,
			'planetName' => 'Homeworld',
			'planetCoords' => '1:2:3',
			'userAgent' => 'TestAgent/1.0',
			'viewport' => '390x844',
			'language' => 'en',
			'version' => '2.0',
			'timestamp' => 1_700_000_000,
		]);

		$this->assertSame('game.php?page=buildings', $ctx['page']);
		$this->assertSame('1', $ctx['universe']);
		$this->assertSame('Tor (#42)', $ctx['account']);
		$this->assertSame('Homeworld [1:2:3] #9', $ctx['planet']);
		$this->assertSame('TestAgent/1.0', $ctx['browser']);
		$this->assertSame('390x844', $ctx['viewport']);
		$this->assertSame('en', $ctx['language']);
		$this->assertSame('2.0', $ctx['version']);
		$this->assertStringContainsString('UTC', $ctx['time']);
	}

	public function testBuildContextGuestWhenLoggedOut(): void
	{
		$ctx = FeedbackService::buildContext([
			'pageUrl' => '/index.php?page=register',
			'universe' => 2,
			'userId' => 0,
			'username' => '',
		]);
		$this->assertSame('guest', $ctx['account']);
		$this->assertSame('n/a', $ctx['planet']);
	}

	public function testValidateDescription(): void
	{
		$this->assertSame('empty_description', FeedbackService::validateDescription('   '));
		$this->assertNull(FeedbackService::validateDescription('Something broke on fleet send.'));
		$this->assertSame(
			'description_too_long',
			FeedbackService::validateDescription(str_repeat('x', FeedbackService::MAX_DESCRIPTION_LENGTH + 1))
		);
	}

	public function testValidateScreenshotRejectsNonImagesAndOversize(): void
	{
		$this->assertNull(FeedbackService::validateScreenshot(null));
		$this->assertNull(FeedbackService::validateScreenshot([
			'error' => UPLOAD_ERR_NO_FILE,
			'size' => 0,
			'type' => '',
			'tmp_name' => '',
		]));

		$this->assertSame('screenshot_too_large', FeedbackService::validateScreenshot([
			'error' => UPLOAD_ERR_OK,
			'size' => FeedbackService::MAX_SCREENSHOT_BYTES + 1,
			'type' => 'image/png',
			'tmp_name' => '',
		]));

		$this->assertSame('screenshot_invalid_type', FeedbackService::validateScreenshot([
			'error' => UPLOAD_ERR_OK,
			'size' => 100,
			'type' => 'application/pdf',
			'tmp_name' => '',
		]));
	}

	public function testRateLimitRemaining(): void
	{
		$now = 1_700_000_000;
		$this->assertSame(0, FeedbackService::rateLimitRemaining(0, $now));
		$this->assertSame(0, FeedbackService::rateLimitRemaining($now - 120, $now));
		$this->assertSame(60, FeedbackService::rateLimitRemaining($now - 60, $now));
	}

	public function testCsrfRoundTrip(): void
	{
		$token = FeedbackService::issueCsrfToken();
		$this->assertNotSame('', $token);
		$this->assertTrue(FeedbackService::validateCsrfToken($token));
		$this->assertFalse(FeedbackService::validateCsrfToken('wrong'));
		$this->assertFalse(FeedbackService::validateCsrfToken(''));
	}

	public function testSubmitCreatesTicketViaFactories(): void
	{
		$created = [];
		$result = FeedbackService::submit(
			'Fleet button missing on mobile',
			'bug',
			FeedbackService::buildContext([
				'pageUrl' => 'game.php?page=fleetTable',
				'universe' => 1,
				'userId' => 7,
				'username' => 'Pilot',
			]),
			7,
			'Pilot',
			null,
			static function (int $ownerId, int $categoryId, string $subject) use (&$created): int {
				$created['ticket'] = compact('ownerId', 'categoryId', 'subject');
				return 99;
			},
			static function (
				int $ticketId,
				int $ownerId,
				string $ownerName,
				string $subject,
				string $message,
				int $status
			) use (&$created): int {
				$created['answer'] = compact('ticketId', 'ownerId', 'ownerName', 'subject', 'message', 'status');
				return 1;
			},
			static fn (): ?string => null
		);

		$this->assertTrue($result['ok']);
		$this->assertSame(99, $result['ticketId']);
		$this->assertSame('#99', $result['reference']);
		$this->assertSame(7, $created['ticket']['ownerId']);
		$this->assertSame(FeedbackService::FEEDBACK_CATEGORY_ID, $created['ticket']['categoryId']);
		$this->assertStringStartsWith('[Bug]', $created['ticket']['subject']);
		$this->assertStringContainsString('Fleet button missing on mobile', $created['answer']['message']);
		$this->assertStringContainsString('Auto-captured context', $created['answer']['message']);
		$this->assertStringContainsString('Pilot (#7)', $created['answer']['message']);
	}

	public function testSubmitRequiresDescription(): void
	{
		$result = FeedbackService::submit(
			'  ',
			'other',
			[],
			0,
			'Guest',
			null,
			static fn (): int => 1,
			static fn (): int => 1
		);
		$this->assertFalse($result['ok']);
		$this->assertSame('empty_description', $result['error']);
	}

	public function testSubjectForType(): void
	{
		$this->assertStringStartsWith('[Idea]', FeedbackService::subjectForType('idea', 'Add dark mode'));
		$this->assertStringStartsWith('[Feedback]', FeedbackService::subjectForType('other', 'Hello'));
	}
}
