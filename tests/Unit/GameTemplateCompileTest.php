<?php

declare(strict_types=1);

use HiveNova\Core\Template;
use PHPUnit\Framework\TestCase;

/**
 * Compile every game Smarty template with the app Template (Smarty) setup.
 * A syntax error such as raw JS `{` braces (#748) fails CI.
 */
class GameTemplateCompileTest extends TestCase
{
	public function testAllGameTemplatesCompile(): void
	{
		$root = ROOT_PATH . 'styles/templates/game/';
		$this->assertDirectoryExists($root);

		$files = $this->gameTemplateFiles($root);
		$this->assertNotEmpty($files);

		$smarty = new Template();
		$smarty->setCaching(\Smarty::CACHING_OFF);
		$smarty->setForceCompile(true);
		$smarty->setCompileCheck(true);
		$compileDir = sys_get_temp_dir() . '/hivenova-game-tpl-compile-' . getmypid() . '/';
		if (!is_dir($compileDir) && !mkdir($compileDir, 0777, true) && !is_dir($compileDir)) {
			$this->fail('Could not create compile dir ' . $compileDir);
		}
		$smarty->setCompileDir($compileDir);
		$smarty->setCacheDir($compileDir . 'cache/');
		$smarty->setTemplateDir($root);
		// Existing templates use these PHP functions as modifiers; register them
		// for compile-only so this test does not surface Smarty deprecations.
		$smarty->registerPlugin('modifier', 'range', 'range');
		$smarty->registerPlugin('modifier', 'array_filter', 'array_filter');


		$failures = [];
		$previous = error_reporting(error_reporting() & ~E_DEPRECATED);
		try {
			foreach ($files as $relative) {
				try {
					$tpl = $smarty->createTemplate($relative);
					$tpl->compileTemplateSource();
				} catch (\Throwable $e) {
					$failures[] = $relative . ': ' . $this->oneLine($e->getMessage());
				}
			}
		} finally {
			error_reporting($previous);
		}

		$this->assertSame(
			[],
			$failures,
			count($failures) . ' of ' . count($files) . " game templates failed to compile:\n" . implode("\n", $failures)
		);
	}

	/**
	 * @return list<string>
	 */
	private function gameTemplateFiles(string $root): array
	{
		$files = [];
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
		);
		foreach ($iterator as $file) {
			if ($file->isFile() && strtolower($file->getExtension()) === 'tpl') {
				$files[] = substr($file->getPathname(), strlen($root));
			}
		}
		sort($files);

		return $files;
	}

	private function oneLine(string $message): string
	{
		return trim(preg_replace('/\s+/', ' ', $message) ?? $message);
	}
}
