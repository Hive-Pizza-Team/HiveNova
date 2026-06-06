<?php

use HiveNova\Core\Language;
use HiveNova\Core\Template;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TemplateTest extends TestCase
{
    private function invokePrivate(Template $template, string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod(Template::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke($template, ...$args);
    }

    private function getWindow(Template $template): string
    {
        $ref = new ReflectionProperty(Template::class, 'window');
        $ref->setAccessible(true);

        return $ref->getValue($template);
    }

    private function setWindow(Template $template, string $window): void
    {
        $ref = new ReflectionProperty(Template::class, 'window');
        $ref->setAccessible(true);
        $ref->setValue($template, $window);
    }

    // -------------------------------------------------------------------------
    // Constructor / smartySettings
    // -------------------------------------------------------------------------

    public function testConstructorAppliesSmartySettings(): void
    {
        $template = new Template();

        $this->assertFalse($template->getForceCompile());
        $this->assertNotFalse($template->getMergeCompiledIncludes());
        $this->assertNotFalse($template->getCompileCheck());
        $this->assertSame(604800, $template->getCacheLifetime());
        $this->assertSame(\Smarty::CACHING_LIFETIME_CURRENT, $template->getCaching());
        $this->assertStringContainsString('styles/templates/', $template->getTemplateDir()[0]);
        $pluginDirs = implode('|', $template->getPluginsDir());
        $this->assertStringContainsString('includes/smarty-plugins/', $pluginDirs);
        $this->assertStringStartsWith(CACHE_PATH, $template->getCompileDir());
        $this->assertSame($template->getCompileDir() . 'templates/', $template->getCacheDir());
    }

    public function testSmartySettingsRegistersModifierPlugins(): void
    {
        $template = new Template();
        $ref = new ReflectionProperty($template, 'registered_plugins');
        $ref->setAccessible(true);
        $modifiers = $ref->getValue($template)['modifier'] ?? [];

        $this->assertArrayHasKey('array_key_first', $modifiers);
        $this->assertArrayHasKey('abs', $modifiers);
        $this->assertArrayHasKey('pretty_fly_time', $modifiers);
        $this->assertSame(5, $modifiers['abs'][0](-5));
        $this->assertSame(3.14, $modifiers['floatval'][0]('3.14abc'));
    }

    // -------------------------------------------------------------------------
    // assign_vars / display helpers
    // -------------------------------------------------------------------------

    public function testAssignVarsMakesVariablesAvailable(): void
    {
        $template = new Template();
        $template->assign_vars(['title' => 'HiveNova', 'count' => 7]);

        $this->assertSame('HiveNova', $template->getTemplateVars('title'));
        $this->assertSame(7, $template->getTemplateVars('count'));
    }

    public function testAssignVarsAcceptsNocacheFlag(): void
    {
        $template = new Template();
        $template->assign_vars(['cached' => 'yes'], false);

        $this->assertSame('yes', $template->getTemplateVars('cached'));
    }

    public function testLoadscriptStripsJsExtension(): void
    {
        $template = new Template();
        $template->loadscript('scripts/game.js');

        $this->assertSame(['scripts/game'], $template->jsscript);
    }

    public function testExecscriptAppendsRawScript(): void
    {
        $template = new Template();
        $template->execscript('alert(1);');
        $template->execscript('console.log("ok");');

        $this->assertSame(['alert(1);', 'console.log("ok");'], $template->script);
    }

    public function testGotosideAssignsRedirectVariables(): void
    {
        $template = new Template();
        $template->gotoside('game.php?page=overview', 5);

        $this->assertSame(5, $template->getTemplateVars('gotoinsec'));
        $this->assertSame('game.php?page=overview', $template->getTemplateVars('goto'));
    }

    public function testDisplaySetsCompileIdFromLanguageBeforeRender(): void
    {
        $lng = new Language('en');
        $GLOBALS['LNG'] = $lng;

        $template = new Template();

        try {
            $template->display('nonexistent_template_xyz.tpl');
            $this->fail('Expected Smarty to reject a missing template.');
        } catch (SmartyException) {
            $this->assertSame('en', $template->compile_id);
        }
    }

    // -------------------------------------------------------------------------
    // getTempPath
    // -------------------------------------------------------------------------

    public function testGetTempPathReturnsWritableDirectory(): void
    {
        $template = new Template();
        $path = $this->invokePrivate($template, 'getTempPath');

        $this->assertIsString($path);
        $this->assertNotSame('', $path);
        $this->assertDirectoryExists(rtrim($path, '/'));
        $this->assertTrue(is_writable(rtrim($path, '/')));
    }

    public function testGetTempPathEnablesForceCompileAndDisablesCaching(): void
    {
        $template = new Template();
        $this->invokePrivate($template, 'getTempPath');

        $this->assertTrue($template->getForceCompile());
        $this->assertSame(\Smarty::CACHING_OFF, $template->getCaching());
    }

    // -------------------------------------------------------------------------
    // Window modes (protected property default / overrides)
    // -------------------------------------------------------------------------

    public function testWindowDefaultsToFull(): void
    {
        $template = new Template();

        $this->assertSame('full', $this->getWindow($template));
    }

    #[DataProvider('windowModeProvider')]
    public function testWindowCanBeSetToAlternateModes(string $mode): void
    {
        $template = new Template();
        $this->setWindow($template, $mode);

        $this->assertSame($mode, $this->getWindow($template));
    }

    /** @return array<string, array{0: string}> */
    public static function windowModeProvider(): array
    {
        return [
            'popup' => ['popup'],
            'ajax' => ['ajax'],
            'bare' => ['bare'],
            'light' => ['light'],
            'standalone' => ['standalone'],
        ];
    }

    // -------------------------------------------------------------------------
    // Smarty magic __get / __set compatibility
    // -------------------------------------------------------------------------

    public function testMagicGetReturnsCompileDir(): void
    {
        $template = new Template();

        $this->assertSame($template->getCompileDir(), $template->compile_dir);
    }

    public function testMagicSetUpdatesTemplateDir(): void
    {
        $template = new Template();
        $customDir = ROOT_PATH . 'styles/templates/game/';
        $template->template_dir = $customDir;

        $this->assertSame($customDir, $template->getTemplateDir()[0]);
    }

    public function testMagicSetAllowsCustomProperty(): void
    {
        $template = new Template();
        $template->custom_flag = true;

        $this->assertTrue($template->custom_flag);
    }
}
