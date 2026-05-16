<?php

declare(strict_types=1);

use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Captures SQL + bound params from destruction fleet/planet steps (no PDO).
 *
 * @internal
 */
final class DestructionPageDatabaseStub implements DatabaseInterface
{
    /** @var list<array{qry: string, params: array}> */
    public array $selects = [];

    /** @var list<array{qry: string, params: array}> */
    public array $updates = [];

    /** @var list<array{qry: string, params: array}> */
    public array $deletes = [];

    private int $lastRowCount = 0;

    private int $queryCounter = 0;

    public function select($qry, array $params = []): array
    {
        $this->selects[] = ['qry' => $qry, 'params' => $params];
        ++$this->queryCounter;
        $this->lastRowCount = 0;

        return [];
    }

    public function selectSingle($qry, array $params = [], $field = false)
    {
        ++$this->queryCounter;
        $this->lastRowCount = 0;

        return null;
    }

    public function insert($qry, array $params = [])
    {
        ++$this->queryCounter;
        $this->lastRowCount = 1;

        return true;
    }

    public function update($qry, array $params = [])
    {
        $this->updates[] = ['qry' => $qry, 'params' => $params];
        ++$this->queryCounter;
        $this->lastRowCount = 2;

        return true;
    }

    public function delete($qry, array $params = [])
    {
        $this->deletes[] = ['qry' => $qry, 'params' => $params];
        ++$this->queryCounter;
        $this->lastRowCount = 3;

        return true;
    }

    public function replace($qry, array $params = [])
    {
        ++$this->queryCounter;
        $this->lastRowCount = 0;

        return true;
    }

    public function query($qry)
    {
        ++$this->queryCounter;
    }

    public function nativeQuery($qry)
    {
        ++$this->queryCounter;

        return [];
    }

    public function lastInsertId()
    {
        return 0;
    }

    public function rowCount()
    {
        return $this->lastRowCount;
    }

    public function getQueryCounter()
    {
        return $this->queryCounter;
    }

    public function quote($str)
    {
        return "'" . addslashes((string) $str) . "'";
    }

    public function disconnect()
    {
    }

    public function beginTransaction(): void
    {
    }

    public function commit(): void
    {
    }

    public function rollback(): void
    {
    }
}

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

    /**
     * Regression: HY093 / native PDO — fleet DELETE must not receive :now; UPDATE must use
     * unique placeholder names for start vs end galaxy (no duplicate :gal).
     *
     * @runInSeparateProcess
     */
    public function testExecuteFleetsGalaxyModeDeleteParamsOmitNowAndUpdateUsesSgalEgal(): void
    {
        if (!defined('AUTH_ADM')) {
            define('AUTH_ADM', 3);
        }
        if (!defined('AUTH_USR')) {
            define('AUTH_USR', 0);
        }
        if (!defined('TIMESTAMP')) {
            define('TIMESTAMP', 1_704_067_200);
        }
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        require_once dirname(__DIR__, 2) . '/includes/GeneralFunctions.php';

        global $USER;
        $USER = ['authlevel' => AUTH_ADM, 'rights' => []];

        $stub = new DestructionPageDatabaseStub();
        Database::setInstance($stub);

        require dirname(__DIR__, 2) . '/includes/pages/adm/ShowDestructionPage.php';

        executeFleets(1, 'galaxy', 2, 99);

        $this->assertCount(1, $stub->deletes);
        $this->assertCount(1, $stub->updates);

        $delParams = $stub->deletes[0]['params'];
        $this->assertArrayNotHasKey(':now', $delParams);
        $this->assertSame([':uni' => 1, ':gal' => 2], $delParams);

        $updParams = $stub->updates[0]['params'];
        $this->assertArrayHasKey(':now', $updParams);
        $this->assertSame(TIMESTAMP, $updParams[':now']);
        $this->assertSame(2, $updParams[':sgal']);
        $this->assertSame(2, $updParams[':egal']);
        $this->assertArrayNotHasKey(':gal', $updParams);
    }

    /**
     * Regression: fleet UPDATE system mode — :sgal/:ssys vs :egal/:esys pairs; DELETE adds :sys.
     *
     * @runInSeparateProcess
     */
    public function testExecuteFleetsSystemModeUsesDistinctStartEndBindings(): void
    {
        if (!defined('AUTH_ADM')) {
            define('AUTH_ADM', 3);
        }
        if (!defined('AUTH_USR')) {
            define('AUTH_USR', 0);
        }
        if (!defined('TIMESTAMP')) {
            define('TIMESTAMP', 1_704_067_200);
        }
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        require_once dirname(__DIR__, 2) . '/includes/GeneralFunctions.php';

        global $USER;
        $USER = ['authlevel' => AUTH_ADM, 'rights' => []];

        $stub = new DestructionPageDatabaseStub();
        Database::setInstance($stub);

        require dirname(__DIR__, 2) . '/includes/pages/adm/ShowDestructionPage.php';

        executeFleets(1, 'system', 2, 5);

        $this->assertSame([':uni' => 1, ':gal' => 2, ':sys' => 5], $stub->deletes[0]['params']);

        $u = $stub->updates[0]['params'];
        $this->assertSame(5, $u[':ssys']);
        $this->assertSame(5, $u[':esys']);
        $this->assertSame(2, $u[':sgal']);
        $this->assertSame(2, $u[':egal']);
        $this->assertArrayNotHasKey(':gal', $u);
    }

    /**
     * Regression: JOIN users+planets WHERE must use p.galaxy / p.system (users also has galaxy/system).
     *
     * @runInSeparateProcess
     */
    public function testExecutePlanetsSelectQualifiesPlanetColumnsForJoin(): void
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
        $USER = ['authlevel' => AUTH_ADM, 'rights' => []];

        $stub = new DestructionPageDatabaseStub();
        Database::setInstance($stub);

        require dirname(__DIR__, 2) . '/includes/pages/adm/ShowDestructionPage.php';

        executePlanets(1, 'galaxy', 2, 0, 0, 0.0, 0.0);

        $this->assertCount(1, $stub->selects);
        $joinSql = $stub->selects[0]['qry'];
        $this->assertStringContainsString('JOIN', $joinSql);
        $this->assertStringContainsString('p.universe = :uni', $joinSql);
        $this->assertStringContainsString('p.galaxy = :gal', $joinSql);
        $this->assertStringNotContainsString('WHERE p.universe = :uni AND galaxy = :gal', $joinSql);

        executePlanets(3, 'system', 2, 7, 0, 0.0, 0.0);

        $joinSql2 = $stub->selects[1]['qry'];
        $this->assertStringContainsString('p.`system` = :sys', $joinSql2);
    }
}
