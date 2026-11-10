<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/.github/scripts/PrScreenshotCheck.php';

final class PrScreenshotCheckTest extends TestCase
{
	public function testRenderingPathsDetectsUiAndPageControllers(): void
	{
		$paths = PrScreenshotCheck::renderingPaths([
			'includes/classes/Foo.php',
			'styles/templates/game/page.overview.default.tpl',
			'scripts/game/base.js',
			'chat/js/chat.js',
			'includes/pages/game/ShowOverviewPage.class.php',
			'install/custom.layout.tpl',
			'language/en/INGAME.php',
			'\\styles\\theme\\hive\\formate.css',
		]);

		$this->assertSame([
			'styles/templates/game/page.overview.default.tpl',
			'scripts/game/base.js',
			'chat/js/chat.js',
			'includes/pages/game/ShowOverviewPage.class.php',
			'install/custom.layout.tpl',
			'styles/theme/hive/formate.css',
		], $paths);
	}

	public function testEvaluateFailsForPageControllerWithoutScreenshot(): void
	{
		$result = PrScreenshotCheck::evaluate(
			['includes/pages/login/ShowRegisterPage.php'],
			'## Summary\nCapture src=',
			[]
		);
		$this->assertFalse($result['ok']);
		$this->assertTrue($result['required']);
	}

	public function testEvaluatePassesWhenNoRenderingFiles(): void
	{
		$result = PrScreenshotCheck::evaluate(
			['includes/classes/Foo.php'],
			'',
			[]
		);
		$this->assertTrue($result['ok']);
		$this->assertFalse($result['required']);
	}

	public function testEvaluateFailsWhenRenderingWithoutScreenshot(): void
	{
		$result = PrScreenshotCheck::evaluate(
			['styles/resource/css/ingame/main.css'],
			"## Summary\nFixes spacing",
			[]
		);
		$this->assertFalse($result['ok']);
		$this->assertTrue($result['required']);
	}

	public function testEvaluatePassesWithMarkdownImage(): void
	{
		$result = PrScreenshotCheck::evaluate(
			['styles/templates/game/main.header.tpl'],
			"Before/after:\n![nav](https://github.com/user-attachments/assets/abc-123)",
			[]
		);
		$this->assertTrue($result['ok']);
	}

	public function testEvaluatePassesWithHtmlImg(): void
	{
		$result = PrScreenshotCheck::evaluate(
			['scripts/game/flotten.js'],
			'<p><img src="https://example.com/shot.png" alt="fleet"></p>',
			[]
		);
		$this->assertTrue($result['ok']);
	}

	public function testEvaluatePassesWithBareUserAttachmentUrl(): void
	{
		$result = PrScreenshotCheck::evaluate(
			['chat/style.css'],
			'See https://github.com/user-attachments/assets/deadbeef-0000-0000-0000-000000000001',
			[]
		);
		$this->assertTrue($result['ok']);
	}

	public function testSkipLabelBypassesRequirement(): void
	{
		$result = PrScreenshotCheck::evaluate(
			['styles/theme/hive/formate.css'],
			'No visual change',
			['skip-screenshot']
		);
		$this->assertTrue($result['ok']);
		$this->assertTrue($result['skipped']);
	}

	public function testSkipMarkerInBodyBypassesRequirement(): void
	{
		$result = PrScreenshotCheck::evaluate(
			['styles/theme/hive/formate.css'],
			'[skip-screenshot] rename only, no visual delta',
			[]
		);
		$this->assertTrue($result['ok']);
		$this->assertTrue($result['skipped']);
	}

	public function testCliEntryFailsWithoutScreenshot(): void
	{
		$php = escapeshellarg(PHP_BINARY);
		$script = escapeshellarg(dirname(__DIR__, 2) . '/.github/scripts/check-pr-screenshot.php');
		$cmd = $php . ' ' . $script . ' --files=styles/a.css --body=' . escapeshellarg('no-image-here');
		exec($cmd . ' 2>&1', $output, $code);
		$this->assertSame(1, $code, implode("\n", $output));
	}

	public function testCliEntryPassesWithScreenshot(): void
	{
		$php = escapeshellarg(PHP_BINARY);
		$script = escapeshellarg(dirname(__DIR__, 2) . '/.github/scripts/check-pr-screenshot.php');
		$cmd = $php . ' ' . $script . ' --files=styles/a.css --body=' . escapeshellarg('![x](https://example.com/x.png)');
		exec($cmd . ' 2>&1', $output, $code);
		$this->assertSame(0, $code, implode("\n", $output));
	}
}
