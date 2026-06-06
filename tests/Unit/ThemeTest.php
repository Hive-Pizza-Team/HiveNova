<?php

use HiveNova\Core\Theme;
use PHPUnit\Framework\TestCase;

class ThemeTest extends TestCase
{
    private bool $hadSessionDpath = false;

    private mixed $savedSessionDpath = null;

    protected function setUp(): void
    {
        if (!defined('DEFAULT_THEME')) {
            define('DEFAULT_THEME', 'hive');
        }

        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        if (array_key_exists('dpath', $_SESSION)) {
            $this->hadSessionDpath = true;
            $this->savedSessionDpath = $_SESSION['dpath'];
        }

        unset($_SESSION['dpath']);
    }

    protected function tearDown(): void
    {
        if ($this->hadSessionDpath) {
            $_SESSION['dpath'] = $this->savedSessionDpath;
        } else {
            unset($_SESSION['dpath']);
        }
    }

    /** @return array<string, mixed> */
    private function getPrivateThemeSettings(Theme $theme): array
    {
        $ref = new ReflectionProperty(Theme::class, 'THEMESETTINGS');
        $ref->setAccessible(true);

        return $ref->getValue($theme);
    }

    private function invokePrivate(Theme $theme, string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod(Theme::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke($theme, ...$args);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testConstructorUsesDefaultThemeWhenSessionUnset(): void
    {
        $theme = new Theme();

        $this->assertSame(DEFAULT_THEME, $theme->getThemeName());
        $this->assertSame('Hive skin', $theme->skininfo['name']);
    }

    public function testConstructorUsesSessionDpathWhenSet(): void
    {
        $_SESSION['dpath'] = 'gow';

        $theme = new Theme();

        $this->assertSame('gow', $theme->getThemeName());
        $this->assertSame('Galaxy of War', $theme->skininfo['name']);
    }

    // -------------------------------------------------------------------------
    // setUserTheme
    // -------------------------------------------------------------------------

    public function testSetUserThemeAcceptsValidTheme(): void
    {
        $theme = new Theme();

        $result = $theme->setUserTheme('nova');

        $this->assertNotFalse($result);
        $this->assertSame('nova', $theme->getThemeName());
        $this->assertSame('SteemNova skin', $theme->skininfo['name']);
        $this->assertSame('nova', $theme->skininfo['tag']);
    }

    public function testSetUserThemeRejectsInvalidTheme(): void
    {
        $theme = new Theme();
        $before = $theme->getThemeName();

        $result = $theme->setUserTheme('not_a_real_theme_xyz');

        $this->assertFalse($result);
        $this->assertSame($before, $theme->getThemeName());
        $this->assertSame('Hive skin', $theme->skininfo['name']);
    }

    // -------------------------------------------------------------------------
    // getTheme / getThemeName / getTemplatePath
    // -------------------------------------------------------------------------

    public function testGetThemeReturnsRelativeThemePath(): void
    {
        $theme = new Theme();
        $theme->setUserTheme('hive');

        $this->assertSame('./styles/theme/hive/', $theme->getTheme());
    }

    public function testGetThemeNameReturnsActiveSkin(): void
    {
        $theme = new Theme();
        $theme->setUserTheme('gow');

        $this->assertSame('gow', $theme->getThemeName());
    }

    public function testGetTemplatePathReturnsAbsoluteTemplateDirectory(): void
    {
        $theme = new Theme();
        $theme->setUserTheme('nova');

        $this->assertSame(ROOT_PATH . '/styles/templates/nova/', $theme->getTemplatePath());
    }

    // -------------------------------------------------------------------------
    // isHome
    // -------------------------------------------------------------------------

    public function testIsHomeSetsHomeTemplatePathAndClearsCustomTemplates(): void
    {
        $theme = new Theme();
        $theme->setUserTheme('hive');
        $theme->customtpls = ['layout.full.tpl'];

        $theme->isHome();

        $this->assertSame(ROOT_PATH . 'styles/home/', $theme->template);
        $this->assertSame([], $theme->customtpls);
    }

    // -------------------------------------------------------------------------
    // isCustomTPL
    // -------------------------------------------------------------------------

    public function testIsCustomTPLReturnsFalseForUnknownTemplate(): void
    {
        $theme = new Theme();
        $theme->setUserTheme('hive');

        $this->assertFalse($theme->isCustomTPL('layout.full.tpl'));
    }

    public function testIsCustomTPLReturnsTrueForListedTemplate(): void
    {
        $theme = new Theme();
        $theme->setUserTheme('hive');
        $theme->customtpls = ['layout.full.tpl', 'page.overview.tpl'];

        $this->assertTrue($theme->isCustomTPL('page.overview.tpl'));
        $this->assertFalse($theme->isCustomTPL('other.tpl'));
    }

    public function testIsCustomTPLReturnsFalseWhenCustomtplsUnset(): void
    {
        $theme = new Theme();
        $ref = new ReflectionProperty(Theme::class, 'customtpls');
        $ref->setAccessible(true);
        $ref->setValue($theme, null);

        $this->assertFalse($theme->isCustomTPL('any.tpl'));
    }

    // -------------------------------------------------------------------------
    // parseStyleCFG
    // -------------------------------------------------------------------------

    public function testParseStyleCFGLoadsSkinMetadataFromStyleCfg(): void
    {
        $theme = new Theme();
        $theme->skin = 'gow';

        $this->invokePrivate($theme, 'parseStyleCFG');

        $this->assertSame('Galaxy of War', $theme->skininfo['name']);
        $this->assertSame('gow', $theme->skininfo['tag']);
        $this->assertSame('Keule', $theme->skininfo['author']);
        $this->assertSame([], $theme->customtpls);
    }

    // -------------------------------------------------------------------------
    // setStyleSettings / getStyleSettings
    // -------------------------------------------------------------------------

    public function testSetStyleSettingsMergesDefaultsWithThemeFile(): void
    {
        $theme = new Theme();
        $theme->skin = 'hive';

        $this->invokePrivate($theme, 'setStyleSettings');
        $settings = $this->getPrivateThemeSettings($theme);

        $this->assertSame(2, $settings['PLANET_ROWS_ON_OVERVIEW']);
        $this->assertSame(2, $settings['SHORTCUT_ROWS_ON_FLEET1']);
        $this->assertSame(2, $settings['COLONY_ROWS_ON_FLEET1']);
        $this->assertSame(1, $settings['ACS_ROWS_ON_FLEET1']);
        $this->assertSame(0, $settings['TOPNAV_SHORTLY_NUMBER']);
    }

    public function testGetStyleSettingsReturnsPrivateThemeSettings(): void
    {
        $theme = new Theme();
        $theme->setUserTheme('nova');

        $settings = $theme->getStyleSettings();

        $this->assertSame($this->getPrivateThemeSettings($theme), $settings);
        $this->assertArrayHasKey('PLANET_ROWS_ON_OVERVIEW', $settings);
    }
}
