<?php

declare(strict_types=1);

use HiveNova\Core\HttpPathResolver;

use PHPUnit\Framework\TestCase;

class HttpPathResolverTest extends TestCase
{
    public function testRootScriptYieldsSlash(): void
    {
        $this->assertSame('/', HttpPathResolver::rootFromScriptName('/index.php'));
    }

    public function testSubdirectoryScriptYieldsTrailingSlash(): void
    {
        $this->assertSame('/uni1/', HttpPathResolver::rootFromScriptName('/uni1/game.php'));
    }

    public function testLobbyPhpAsScriptNameYieldsSlash(): void
    {
        $this->assertSame('/', HttpPathResolver::rootFromScriptName('/lobby.php'));
    }

    public function testRewrittenLobbyUsesScriptNameNotRequestUri(): void
    {
        $scriptName = '/index.php';
        $requestUri = '/lobby.php';

        $this->assertSame('/', HttpPathResolver::rootFromScriptName($scriptName));
        $this->assertNotSame(
            $requestUri,
            HttpPathResolver::rootFromScriptName($scriptName)
        );
    }

    public function testWindowsBackslashesAreNormalized(): void
    {
        $this->assertSame('/', HttpPathResolver::rootFromScriptName('\\index.php'));
        $this->assertSame('/uni1/', HttpPathResolver::rootFromScriptName('\\uni1\\game.php'));
    }

    public function testEmptyAndRootScriptNameYieldSlash(): void
    {
        $this->assertSame('/', HttpPathResolver::rootFromScriptName(''));
        $this->assertSame('/', HttpPathResolver::rootFromScriptName('/'));
        $this->assertSame('/', HttpPathResolver::rootFromScriptName('index.php'));
    }

    public function testAbsoluteDoesNotGlueFilenameOntoRelative(): void
    {
        $joined = HttpPathResolver::absolute(
            'https://novadev.hive.pizza/lobby.php',
            'install/index.php?mode=upgrade'
        );

        $this->assertStringNotContainsString('lobby.phpinstall', $joined);
        $this->assertSame(
            'https://novadev.hive.pizza/lobby.php/install/index.php?mode=upgrade',
            $joined
        );
    }

    public function testAbsoluteJoinsHostRootAndRelative(): void
    {
        $this->assertSame(
            'https://host/install/index.php?mode=upgrade',
            HttpPathResolver::absolute('https://host/', 'install/index.php?mode=upgrade')
        );
    }

    public function testAbsoluteReturnsUrlThatAlreadyHasScheme(): void
    {
        $url = 'https://other.example/path';
        $this->assertSame($url, HttpPathResolver::absolute('https://host/', $url));
    }
}
