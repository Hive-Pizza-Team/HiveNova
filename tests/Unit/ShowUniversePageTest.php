<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Universe admin: create/open/close/delete must POST. GET links were ignored
 * after request-type checking, so "Add universe" appeared to do nothing.
 */
final class ShowUniversePageTest extends TestCase
{
    public function testAdminPhpRoutesUniversePage(): void
    {
        $admin = file_get_contents(dirname(__DIR__, 2) . '/admin.php');
        $this->assertStringContainsString("case 'universe':", $admin);
        $this->assertStringContainsString('ShowUniversePage.php', $admin);
        $this->assertStringContainsString('ShowUniversePage()', $admin);
    }

    public function testUniverseTemplatePostsMutatingActions(): void
    {
        $path = dirname(__DIR__, 2) . '/styles/templates/adm/UniversePage.tpl';
        $this->assertFileExists($path);
        $tpl = file_get_contents($path);

        $this->assertStringContainsString('method="post"', $tpl);
        $this->assertStringContainsString('name="action" value="create"', $tpl);
        $this->assertStringContainsString('name="action" value="open"', $tpl);
        $this->assertStringContainsString('name="action" value="closed"', $tpl);
        $this->assertStringContainsString('name="action" value="delete"', $tpl);
        $this->assertStringContainsString('uvs_new', $tpl);

        $this->assertDoesNotMatchRegularExpression('/href="[^"]*action=create/', $tpl);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*action=open/', $tpl);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*action=closed/', $tpl);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*action=delete/', $tpl);
    }

    public function testUniversePageHandlesActionsOnlyOnPost(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/includes/pages/adm/ShowUniversePage.php');
        $this->assertStringContainsString("(\$_SERVER['REQUEST_METHOD'] ?? '') === 'POST'", $src);
        $this->assertStringContainsString("case 'create':", $src);
        $this->assertStringContainsString("case 'open':", $src);
        $this->assertStringContainsString("case 'closed':", $src);
        $this->assertStringContainsString("case 'delete':", $src);
        $this->assertStringContainsString("HTTP::_GP('sid', '')", $src);
        $this->assertStringContainsString('UniverseRewriteProbe', $src);
        $this->assertStringContainsString('skip_rewrite_check', $src);
        $this->assertStringContainsString('{CADDY-CODE}', $src);
        $this->assertStringNotContainsString('$httpCode != 302', $src);
    }
}
