<?php

use HiveNova\Core\Config;
use HiveNova\Core\Language;
use HiveNova\Core\Template;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TemplateTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('DEFAULT_THEME')) {
            define('DEFAULT_THEME', 'hive');
        }
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['LNG'], $GLOBALS['THEME'], $GLOBALS['USER']);

        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    private function seedLanguage(array $files = ['INGAME']): Language
    {
        $lng = new Language('en');
        $lng->includeData($files);

        return $lng;
    }

    /** @return object{isCustomTPL: callable, getTheme: callable, getTemplatePath: callable} */
    private function makeThemeStub(bool $customTpl = false, ?string $templatePath = null): object
    {
        $path = $templatePath ?? ROOT_PATH . 'styles/templates/install/';

        return new class($customTpl, $path) {
            public function __construct(private bool $customTpl, private string $templatePath) {}

            public function isCustomTPL(string $tpl): bool
            {
                return $this->customTpl;
            }

            public function getTheme(): string
            {
                return './styles/theme/hive/';
            }

            public function getTemplatePath(): string
            {
                return $this->templatePath;
            }
        };
    }

    private function seedConfig(): void
    {
        Config::setInstance(new Config([
            'game_name' => 'HiveNova',
            'VERSION'   => '1.0.0.0',
            'uni'       => 1,
        ]), 1);
    }

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

    public function testAssignVarsDefaultsToNocacheTrue(): void
    {
        $template = new Template();
        $template->assign_vars(['live' => 'value']);

        $this->assertSame('value', $template->getTemplateVars('live'));
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

    public function testGotosideDefaultsRedirectDelayToThreeSeconds(): void
    {
        $template = new Template();
        $template->gotoside('game.php?page=overview');

        $this->assertSame(3, $template->getTemplateVars('gotoinsec'));
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

    #[DataProvider('smartyMagicPropertyProvider')]
    public function testMagicGetReturnsSmartyDirectoryGetters(string $property, string $getter): void
    {
        $template = new Template();

        $this->assertSame($template->{$getter}(), $template->{$property});
    }

    public function testMagicGetReturnsProtectedWindowProperty(): void
    {
        $template = new Template();

        $this->assertSame('full', $template->window);
    }

    public function testMagicSetUpdatesTemplateDir(): void
    {
        $template = new Template();
        $customDir = ROOT_PATH . 'styles/templates/game/';
        $template->template_dir = $customDir;

        $this->assertSame($customDir, $template->getTemplateDir()[0]);
    }

    #[DataProvider('smartyMagicPropertyProvider')]
    public function testMagicSetUpdatesSmartyDirectorySetters(string $property, string $getter): void
    {
        $template = new Template();
        $value = ROOT_PATH . 'cache/custom-' . $property . '/';

        $template->{$property} = $value;

        $actual = $template->{$getter}();
        if (is_array($actual)) {
            $this->assertSame($value, $actual[0]);
        } else {
            $this->assertSame($value, $actual);
        }
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function smartyMagicPropertyProvider(): array
    {
        return [
            'template_dir' => ['template_dir', 'getTemplateDir'],
            'config_dir'   => ['config_dir', 'getConfigDir'],
            'plugins_dir'  => ['plugins_dir', 'getPluginsDir'],
            'compile_dir'  => ['compile_dir', 'getCompileDir'],
            'cache_dir'    => ['cache_dir', 'getCacheDir'],
        ];
    }

    public function testMagicSetAllowsCustomProperty(): void
    {
        $template = new Template();
        $template->custom_flag = true;

        $this->assertTrue($template->custom_flag);
    }

    // -------------------------------------------------------------------------
    // adm_main
    // -------------------------------------------------------------------------

    public function testAdmMainAssignsAdminLayoutVariables(): void
    {
        $this->seedConfig();
        $GLOBALS['LNG'] = $this->seedLanguage(['INGAME', 'ADMIN']);
        $GLOBALS['USER'] = ['timezone' => 'Europe/Berlin'];

        $template = new Template();
        $this->invokePrivate($template, 'adm_main');

        $this->assertSame('HiveNova - Administration Panel', $template->getTemplateVars('title'));
        $this->assertSame('Info', $template->getTemplateVars('fcm_info'));
        $this->assertSame('en', $template->getTemplateVars('lang'));
        $this->assertSame('.0.0', $template->getTemplateVars('REV'));
        $this->assertSame('1.0.0.0', $template->getTemplateVars('VERSION'));
        $this->assertSame('styles/theme/hive/', $template->getTemplateVars('dpath'));
        $this->assertSame('full', $template->getTemplateVars('bodyclass'));
        $this->assertIsArray($template->getTemplateVars('date'));
        $this->assertIsInt($template->getTemplateVars('Offset'));
    }

    public function testAdmMainFallsBackWhenTimezoneIsInvalid(): void
    {
        $this->seedConfig();
        $GLOBALS['LNG'] = $this->seedLanguage(['INGAME', 'ADMIN']);
        $GLOBALS['USER'] = ['timezone' => 'Not/A_Timezone'];

        $template = new Template();
        $this->invokePrivate($template, 'adm_main');

        $this->assertSame(0, $template->getTemplateVars('Offset'));
    }

    public function testAdmMainUsesServerTimeWhenUserTimezoneMissing(): void
    {
        $this->seedConfig();
        $GLOBALS['LNG'] = $this->seedLanguage(['INGAME', 'ADMIN']);
        $GLOBALS['USER'] = [];

        $template = new Template();
        $this->invokePrivate($template, 'adm_main');

        $this->assertSame(0, $template->getTemplateVars('Offset'));
        $this->assertSame([], $template->getTemplateVars('scripts'));
    }

    // -------------------------------------------------------------------------
    // show / message
    // -------------------------------------------------------------------------

    public function testShowRendersInstallTemplateWithAssignedScripts(): void
    {
        $GLOBALS['LNG'] = $this->seedLanguage(['INGAME']);
        $GLOBALS['THEME'] = $this->makeThemeStub();

        $template = new Template();
        $template->setCaching(\Smarty::CACHING_OFF);
        $template->loadscript('scripts/game.js');
        $template->execscript('console.log("render");');

        ob_start();
        $template->show('error_message_body.tpl');
        $html = ob_get_clean();

        $this->assertStringContainsString('console.log("render");', $html);
        $this->assertSame(['scripts/game'], $template->getTemplateVars('scripts'));
        $this->assertSame("console.log(\"render\");", $template->getTemplateVars('execscript'));
        $this->assertSame('en', $template->compile_id);
    }

    public function testShowUsesCustomThemeTemplateDirectory(): void
    {
        $customPath = ROOT_PATH . 'styles/templates/';
        $GLOBALS['LNG'] = $this->seedLanguage(['INGAME']);
        $GLOBALS['THEME'] = $this->makeThemeStub(true, $customPath);

        $template = new Template();
        $template->setCaching(\Smarty::CACHING_OFF);

        ob_start();
        $template->show('error_message_body.tpl');
        ob_end_clean();

        $this->assertSame($customPath . 'install/', $template->getTemplateDir()[0]);
    }

    public function testMessageAssignsFatalFlagAndRendersErrorTemplate(): void
    {
        $GLOBALS['LNG'] = $this->seedLanguage(['INGAME']);
        $GLOBALS['THEME'] = $this->makeThemeStub();

        $template = new Template();
        $template->setCaching(\Smarty::CACHING_OFF);

        ob_start();
        $template->message('Something went wrong', 'game.php?page=overview', 7, true);
        ob_end_clean();

        $this->assertSame('Something went wrong', $template->getTemplateVars('mes'));
        $this->assertTrue($template->getTemplateVars('Fatal'));
        $this->assertSame('./styles/theme/hive/', $template->getTemplateVars('dpath'));
        $this->assertSame('Info', $template->getTemplateVars('fcm_info'));
        $this->assertSame(7, $template->getTemplateVars('gotoinsec'));
        $this->assertSame('game.php?page=overview', $template->getTemplateVars('goto'));
    }
}
