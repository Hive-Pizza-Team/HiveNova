<?php

declare(strict_types=1);

use HiveNova\Core\AssetRevision;
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

	public function testBuildContextEscapesRawFieldsOnce(): void
	{
		$ctx = FeedbackService::buildContext([
			'pageUrl' => 'game.php?page=overview&x=<script>',
			'userId' => 1,
			'username' => 'A&B',
			'planetName' => 'X"Y',
			'userAgent' => 'Mozilla/5.0 <Evil>',
			'viewport' => '100x200',
		]);

		$this->assertStringContainsString('A&amp;B', $ctx['account']);
		$this->assertStringContainsString('X&quot;Y', $ctx['planet']);
		$this->assertSame('Mozilla/5.0 &lt;Evil&gt;', $ctx['browser']);
		$this->assertStringNotContainsString('<script>', $ctx['page']);
		$this->assertStringContainsString('%3Cscript%3E', $ctx['page']);
	}

	public function testScreenshotFilenameValidationAndLinkify(): void
	{
		$ok = 'fb_99_0123456789abcdef.png';
		$this->assertTrue(FeedbackService::isValidScreenshotFilename($ok));
		$this->assertTrue(FeedbackService::isValidScreenshotFilename('cache/feedback/' . $ok));
		$this->assertFalse(FeedbackService::isValidScreenshotFilename('../etc/passwd'));
		$this->assertFalse(FeedbackService::isValidScreenshotFilename('fb_99_short.png'));
		$this->assertFalse(FeedbackService::isValidScreenshotFilename('fb_99_0123456789abcdef.php'));

		$linked = FeedbackService::linkifyScreenshotReferences(
			"Hello\nScreenshot: cache/feedback/{$ok}\nDone"
		);
		$this->assertStringContainsString('mode=screenshot', $linked);
		$this->assertStringContainsString('file=' . $ok, $linked);
		$this->assertStringContainsString('<a href=', $linked);
		$this->assertSame('image/png', FeedbackService::screenshotContentType($ok));
		$this->assertSame('image/jpeg', FeedbackService::screenshotContentType('fb_1_0123456789abcdef.jpg'));
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
		$long = str_repeat('word ', 30);
		$this->assertStringContainsString('...', FeedbackService::subjectForType('bug', $long));
		$this->assertSame('[Bug] Player feedback', FeedbackService::subjectForType('bug', '   '));
	}

	public function testScreenshotHelpersAndCsrf(): void
	{
		$this->assertFalse(FeedbackService::isValidScreenshotFilename('../etc/passwd'));
		$this->assertNull(FeedbackService::screenshotAbsolutePath('../nope.png'));
		$this->assertSame('image/jpeg', FeedbackService::screenshotContentType('shot.jpg'));
		$this->assertSame('image/gif', FeedbackService::screenshotContentType('shot.gif'));
		$this->assertSame('image/webp', FeedbackService::screenshotContentType('shot.webp'));
		$this->assertSame('image/png', FeedbackService::screenshotContentType('shot.png'));

		$file = 'fb_12_0123456789abcdef.png';
		$linked = FeedbackService::linkifyScreenshotReferences('Screenshot: cache/feedback/'.$file);
		$this->assertStringContainsString('admin.php?page=support', $linked);
		$this->assertStringContainsString($file, $linked);
		$this->assertSame('plain', FeedbackService::linkifyScreenshotReferences('plain'));

		$token = FeedbackService::issueCsrfToken();
		$this->assertTrue(FeedbackService::validateCsrfToken($token));
		$this->assertFalse(FeedbackService::validateCsrfToken('nope'));
		$this->assertFalse(FeedbackService::validateCsrfToken(null));
		unset($_SESSION[FeedbackService::CSRF_SESSION_KEY]);
		$this->assertFalse(FeedbackService::validateCsrfToken($token));

		$this->assertSame(0, FeedbackService::lastSubmitAtFromSession());
		FeedbackService::rememberSubmitAt(100);
		$this->assertSame(100, FeedbackService::lastSubmitAtFromSession());
		$this->assertSame(0, FeedbackService::rateLimitRemaining(0, 200));
		$this->assertGreaterThan(0, FeedbackService::rateLimitRemaining(100, 100));
	}

	public function testOriginChecksAndScreenshotValidation(): void
	{
		$_SERVER['HTTP_HOST'] = 'moon.hive.pizza';
		unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_REFERER']);
		$this->assertTrue(FeedbackService::isSameOriginRequest());

		$_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
		$this->assertFalse(FeedbackService::isSameOriginRequest());
		$_SERVER['HTTP_ORIGIN'] = 'https://moon.hive.pizza';
		$this->assertTrue(FeedbackService::isSameOriginRequest());
		unset($_SERVER['HTTP_ORIGIN']);
		$_SERVER['HTTP_REFERER'] = 'https://moon.hive.pizza/game.php';
		$this->assertTrue(FeedbackService::isSameOriginRequest());
		$_SERVER['HTTP_REFERER'] = 'https://evil.example/';
		$this->assertFalse(FeedbackService::isSameOriginRequest());

		$this->assertNull(FeedbackService::validateDescription('ok'));
		$this->assertSame('empty_description', FeedbackService::validateDescription('  '));
		$this->assertSame('description_too_long', FeedbackService::validateDescription(str_repeat('a', FeedbackService::MAX_DESCRIPTION_LENGTH + 1)));
		$this->assertNull(FeedbackService::validateScreenshot(null));
		$this->assertNull(FeedbackService::validateScreenshot([]));
		$this->assertSame('screenshot_upload_failed', FeedbackService::validateScreenshot([
			'error' => UPLOAD_ERR_INI_SIZE,
		]));
		$this->assertSame('screenshot_upload_failed', FeedbackService::validateScreenshot([
			'error' => UPLOAD_ERR_OK,
			'size' => 0,
		]));
		$this->assertSame('screenshot_too_large', FeedbackService::validateScreenshot([
			'error' => UPLOAD_ERR_OK,
			'size' => FeedbackService::MAX_SCREENSHOT_BYTES + 1,
			'type' => 'image/png',
		]));
		$this->assertSame('screenshot_invalid_type', FeedbackService::validateScreenshot([
			'error' => UPLOAD_ERR_OK,
			'size' => 10,
			'type' => 'text/plain',
		]));
		$this->assertNull(FeedbackService::validateScreenshot([
			'error' => UPLOAD_ERR_OK,
			'size' => 10,
			'type' => 'image/png',
		]));

		$this->assertSame('#9', FeedbackService::referenceId(9));
		$text = FeedbackService::formatContextText(['language' => 'en', 'version' => '2', 'time' => 'now']);
		$this->assertStringContainsString('Language: en', $text);
		$this->assertSame('game.php', FeedbackService::stripSensitiveQueryString('game.php'));
		$this->assertSame('game.php', FeedbackService::stripSensitiveQueryString('game.php?password=x'));
		$this->assertSame(['page' => 'a'], FeedbackService::stripSensitiveQueryParams(['password' => 'x', 'page' => 'a']));
		$this->assertSame(
			['keep' => ['page' => 'overview']],
			FeedbackService::stripSensitiveQueryParams(['keep' => ['page' => 'overview', 'token' => 'x']])
		);
		$this->assertSame('', FeedbackService::sanitizeUrl(''));
		$this->assertSame('game.php?page=overview', FeedbackService::stripSensitiveQueryString('game.php?page=overview'));

		$context = FeedbackService::buildContext([
			'userAgent' => str_repeat('A', 600),
			'planetName' => 'Home',
			'planetCoords' => '1:2:3',
			'userId' => 4,
			'username' => 'Ann',
		]);
		$this->assertSame(512, strlen($context['browser']));
		$this->assertStringContainsString('Home', $context['planet']);
	}

	public function testStoresScreenshotAndSubmitsTicket(): void
	{
		$paths = AssetRevision::fingerprintPaths();
		$this->assertContains('styles/resource/css/feedback.css', $paths);

		$feedbackCss = file_get_contents(ROOT_PATH . 'styles/resource/css/feedback.css');
		$this->assertIsString($feedbackCss);
		$this->assertMatchesRegularExpression(
			'/\.feedback-fab__icon\s*\{[^}]*background:\s*var\(--color-feedback-fab/s',
			$feedbackCss,
			'FAB ? icon must not use theme --color-accent (Hive red reads as error; #787)'
		);
		$this->assertDoesNotMatchRegularExpression(
			'/\.feedback-fab__icon\s*\{[^}]*background:\s*var\(--color-accent/s',
			$feedbackCss
		);

		$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
		$tmp = tempnam(sys_get_temp_dir(), 'fb');
		$this->assertNotFalse($tmp);
		file_put_contents($tmp, $png);
		$file = [
			'error' => UPLOAD_ERR_OK,
			'size' => strlen($png),
			'type' => 'image/png',
			'tmp_name' => $tmp,
		];
		$this->assertNull(FeedbackService::validateScreenshot($file));

		$stored = FeedbackService::storeScreenshot($file, 7);
		$this->assertNotNull($stored);
		$this->assertStringStartsWith('cache/feedback/fb_7_', $stored);
		$basename = basename($stored);
		$this->assertNotNull(FeedbackService::screenshotAbsolutePath($basename));
		$this->assertNull(FeedbackService::screenshotAbsolutePath('fb_1_0123456789abcdef.png'));

		$this->assertNull(FeedbackService::storeScreenshot(null, 1));
		$this->assertNull(FeedbackService::storeScreenshot(['error' => UPLOAD_ERR_NO_FILE], 1));
		$this->assertNull(FeedbackService::storeScreenshot([
			'error' => UPLOAD_ERR_OK,
			'size' => 10,
			'type' => 'image/png',
			'tmp_name' => '',
		], 1));

		$failed = FeedbackService::submit('Hello', 'bug', [], 0, '', null, static fn (): int => 0);
		$this->assertSame('submit_failed', $failed['error']);

		$ok = FeedbackService::submit(
			'Hello',
			'bug',
			['language' => 'en'],
			3,
			'',
			null,
			static fn (): int => 15,
			static fn (): int => 1
		);
		$this->assertTrue($ok['ok']);
		$this->assertSame('#15', $ok['reference']);

		$config = FeedbackService::clientConfig(
			'game.php?page=feedback',
			['id' => 3, 'username' => 'Ann'],
			['id' => 9, 'name' => 'Home', 'galaxy' => 1, 'system' => 2, 'planet' => 3],
			'en',
			'2',
			1,
			['title' => 'Feedback']
		);
		$this->assertSame('1:2:3', $config['context']['planetCoords']);
		$this->assertSame('Ann', $config['context']['username']);

		$root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2).'/';
		@unlink($root.$stored);
	}
}
