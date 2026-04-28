<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Admin destruction tool: ensure the page module loads for authorized users
 * and wiring (admin route, menu) stays present.
 */
final class ShowDestructionPageTest extends TestCase
{
    public function testAdminPhpRoutesDestructionPage(): void
    {
        $admin = file_get_contents(dirname(__DIR__, 2) . '/admin.php');
        $this->assertStringContainsString("case 'destruction':", $admin);
        $this->assertStringContainsString('ShowDestructionPage.php', $admin);
        $this->assertStringContainsString('ShowDestructionPage()', $admin);
    }

    public function testAdminMenuLinksDestructionPage(): void
    {
        $menu = file_get_contents(dirname(__DIR__, 2) . '/styles/templates/adm/ShowMenuPage.tpl');
        $this->assertStringContainsString("ShowDestructionPage", $menu);
        $this->assertStringContainsString('?page=destruction', $menu);
        $this->assertStringContainsString('mu_destruction', $menu);
    }

    public function testDestructionTemplateExists(): void
    {
        $path = dirname(__DIR__, 2) . '/styles/templates/adm/DestructionPage.tpl';
        $this->assertFileExists($path);
        $tpl = file_get_contents($path);
        $this->assertStringContainsString('page=destruction', $tpl);
        $this->assertStringContainsString('dest_title', $tpl);
        $this->assertStringContainsString('dest_story_title', $tpl);
        $this->assertStringContainsString('dest_story_body', $tpl);
        $this->assertStringContainsString('dest_spawn_apply', $tpl);
        $this->assertStringContainsString('dest_spawn_help', $tpl);
        $this->assertStringContainsString('dest_spawn_coords_heading', $tpl);
        $this->assertStringContainsString('id="spawn_apply"', $tpl);
        $this->assertStringContainsString('row_spawn_coords', $tpl);
        $this->assertStringContainsString('dest_preview_continue', $tpl);
        $this->assertStringContainsString('accept_review', $tpl);
        $this->assertStringContainsString('cancel_preview', $tpl);
        $this->assertStringContainsString('dest_review_execute', $tpl);
        $this->assertStringContainsString('review_token', $tpl);
        $this->assertStringContainsString('backup_before', $tpl);
        $this->assertStringContainsString('cancel_review', $tpl);
        $this->assertStringContainsString('dest_backup_saved', $tpl);
    }

    public function testEnglishAdminLanguageHasDestructionKeys(): void
    {
        $path = dirname(__DIR__, 2) . '/language/en/ADMIN.php';
        $this->assertFileExists($path);
        $src = file_get_contents($path);
        $this->assertStringContainsString("\$LNG['mu_destruction']", $src);
        $this->assertStringContainsString("\$LNG['dest_title']", $src);
        $this->assertStringContainsString("\$LNG['dest_story_title']", $src);
        $this->assertStringContainsString("\$LNG['dest_spawn_coords_heading']", $src);
        $this->assertStringContainsString("\$LNG['dest_backup_before_label']", $src);
        $this->assertStringContainsString("\$LNG['dest_review_expired']", $src);
        $this->assertStringContainsString("\$LNG['dest_spawn_incomplete']", $src);
    }

    public function testShowDestructionPageSourceDefinesWorkflow(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/includes/pages/adm/ShowDestructionPage.php');
        $this->assertStringContainsString('DESTRUCTION_SESSION_KEY', $src);
        $this->assertStringContainsString("'accept_review'", $src);
        $this->assertStringContainsString("'cancel_preview'", $src);
        $this->assertStringContainsString("'cancel_review'", $src);
        $this->assertStringContainsString('review_token', $src);
        $this->assertStringContainsString('hash_equals', $src);
        $this->assertStringContainsString('runDestructionDatabaseBackup', $src);
        $this->assertStringContainsString('applySpawnCursor', $src);
        $this->assertStringContainsString('destructionSqlPreviewLines', $src);
        $this->assertStringContainsString('destructionPackParams', $src);
        $this->assertStringContainsString('destructionValidateInputs', $src);
        $this->assertStringContainsString('backup_file', $src);
        $this->assertStringContainsString('?string $backupRelPath', $src);
    }

    public function testSmoketestListsDestructionPage(): void
    {
        $path = dirname(__DIR__, 2) . '/tests/smoke.php';
        $this->assertFileExists($path);
        $src = file_get_contents($path);
        $this->assertMatchesRegularExpression("/'destruction'/", $src, 'smoke.php should GET admin.php?page=destruction');
    }

    /**
     * Module must load when the current user is an admin (same pattern as ShowBuildLogPage).
     *
     * @runInSeparateProcess
     */
    public function testDestructionPageFileLoadsForAdministrator(): void
    {
        // Only auth constants needed for allowedTo(); avoid loading includes/constants.php
        // here (PHPUnit bootstrap + constants.php both define engine symbols).
        if (!defined('AUTH_ADM')) {
            define('AUTH_ADM', 3);
        }
        if (!defined('AUTH_USR')) {
            define('AUTH_USR', 0);
        }
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        require_once dirname(__DIR__, 2) . '/includes/GeneralFunctions.php';

        global $USER;
        $USER = ['authlevel' => AUTH_ADM, 'rights' => []];

        require dirname(__DIR__, 2) . '/includes/pages/adm/ShowDestructionPage.php';

        $this->assertTrue(function_exists('ShowDestructionPage'));
        $this->assertTrue(function_exists('destructionPreview'));
        $this->assertTrue(function_exists('findFreeSlot'));
        $this->assertTrue(function_exists('findRandomFreeSlot'));
    }

    /**
     * Non-admins without page right must not load the module.
     *
     * @runInSeparateProcess
     */
    public function testDestructionPageThrowsForPlainUser(): void
    {
        if (!defined('AUTH_ADM')) {
            define('AUTH_ADM', 3);
        }
        if (!defined('AUTH_USR')) {
            define('AUTH_USR', 0);
        }
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        require_once dirname(__DIR__, 2) . '/includes/GeneralFunctions.php';

        global $USER;
        $USER = [
            'authlevel' => AUTH_USR,
            'rights'    => ['ShowDestructionPage' => 0],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Permission error!');

        require dirname(__DIR__, 2) . '/includes/pages/adm/ShowDestructionPage.php';
    }

    /**
     * Moderators with rights[ShowDestructionPage] = 1 may use the tool (see allowedTo()).
     *
     * @runInSeparateProcess
     */
    public function testDestructionPageFileLoadsForModeratorWithRight(): void
    {
        if (!defined('AUTH_ADM')) {
            define('AUTH_ADM', 3);
        }
        if (!defined('AUTH_MOD')) {
            define('AUTH_MOD', 1);
        }
        if (!defined('AUTH_USR')) {
            define('AUTH_USR', 0);
        }
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        require_once dirname(__DIR__, 2) . '/includes/GeneralFunctions.php';

        global $USER;
        $USER = [
            'authlevel' => AUTH_MOD,
            'rights'    => ['ShowDestructionPage' => 1],
        ];

        require dirname(__DIR__, 2) . '/includes/pages/adm/ShowDestructionPage.php';

        $this->assertTrue(function_exists('ShowDestructionPage'));
    }

    /**
     * destructionValidateInputs / destructionSqlPreviewLines — logic guards preview & execution paths.
     *
     * @runInSeparateProcess
     */
    public function testDestructionValidateInputsAndSqlPreviewHelpers(): void
    {
        if (!defined('AUTH_ADM')) {
            define('AUTH_ADM', 3);
        }
        if (!defined('AUTH_USR')) {
            define('AUTH_USR', 0);
        }
        if (!defined('DB_PREFIX')) {
            define('DB_PREFIX', 'uni1_');
        }
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        require_once dirname(__DIR__, 2) . '/includes/GeneralFunctions.php';

        global $USER;
        $USER = ['authlevel' => AUTH_ADM, 'rights' => []];

        require dirname(__DIR__, 2) . '/includes/pages/adm/ShowDestructionPage.php';

        $full = [
            'universe'       => 1,
            'mode'           => 'galaxy',
            'galaxy'         => 2,
            'system'         => 0,
            'relocate'       => 0,
            'relocMode'      => 'random',
            'relocGal'       => 0,
            'relocSys'       => 0,
            'relocSlot'      => 0,
            'debris'         => 1,
            'debris_metal'   => 1000.0,
            'debris_crystal' => 0.0,
            'broadcast'      => 0,
            'message'        => '',
            'spawn_apply'    => 0,
            'spawn_galaxy'   => 0,
            'spawn_system'   => 0,
            'spawn_planet'   => 0,
        ];

        $this->assertTrue(destructionValidateInputs(destructionUnpackParams($full)));

        $systemOk = array_merge($full, ['mode' => 'system', 'system' => 5]);
        $this->assertTrue(destructionValidateInputs(destructionUnpackParams($systemOk)));

        $systemBad = array_merge($full, ['mode' => 'system', 'system' => 0]);
        $this->assertFalse(destructionValidateInputs(destructionUnpackParams($systemBad)));

        $lines = destructionSqlPreviewLines(1, 'galaxy', 3, 1, 0, false, 1, 1, 1_000_000.0, 500_000.0);
        $this->assertNotSame([], $lines);
        $blob = implode("\n", $lines);
        $this->assertStringContainsString(DB_PREFIX, $blob);
        $this->assertStringContainsString('DELETE FROM', $blob);

        $withSpawn = destructionSqlPreviewLines(1, 'system', 2, 4, 1, true, 1, 0, 0.0, 0.0);
        $blobSpawn = implode("\n", $withSpawn);
        $this->assertStringContainsString('LastSetted', $blobSpawn);
    }

    /**
     * @runInSeparateProcess
     */
    public function testDestructionPageThrowsForModeratorWithoutRight(): void
    {
        if (!defined('AUTH_ADM')) {
            define('AUTH_ADM', 3);
        }
        if (!defined('AUTH_MOD')) {
            define('AUTH_MOD', 1);
        }
        if (!defined('AUTH_USR')) {
            define('AUTH_USR', 0);
        }
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        require_once dirname(__DIR__, 2) . '/includes/GeneralFunctions.php';

        global $USER;
        $USER = [
            'authlevel' => AUTH_MOD,
            'rights'    => ['ShowDestructionPage' => 0],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Permission error!');

        require dirname(__DIR__, 2) . '/includes/pages/adm/ShowDestructionPage.php';
    }
}
