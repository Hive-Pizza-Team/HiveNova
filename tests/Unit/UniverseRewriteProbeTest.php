<?php

declare(strict_types=1);

use HiveNova\Core\UniverseRewriteProbe;

use PHPUnit\Framework\TestCase;

class UniverseRewriteProbeTest extends TestCase
{
    public function testProbeSkippedForWildcardSubdomains(): void
    {
        $this->assertFalse(UniverseRewriteProbe::isRequired(1, true));
    }

    public function testProbeSkippedWhenMultipleUniversesExist(): void
    {
        $this->assertFalse(UniverseRewriteProbe::isRequired(2, false));
        $this->assertFalse(UniverseRewriteProbe::isRequired(5, false));
    }

    public function testProbeRequiredForFirstExtraUniverseOnPathRouting(): void
    {
        $this->assertTrue(UniverseRewriteProbe::isRequired(1, false));
        $this->assertTrue(UniverseRewriteProbe::isRequired(0, false));
    }

    public function testUrlBuildsUniRootPath(): void
    {
        $this->assertSame(
            'https://moon.hive.pizza/uni1/',
            UniverseRewriteProbe::url('https://', 'moon.hive.pizza', '/', 1)
        );
    }

    public function testInternalRewrite200IsConfigured(): void
    {
        $this->assertTrue(UniverseRewriteProbe::rewriteLooksConfigured(200));
    }

    public function testLegacyApache302WithoutFollowIsNotFinalSuccess(): void
    {
        $this->assertFalse(UniverseRewriteProbe::rewriteLooksConfigured(302));
    }

    public function testMissingRouteIsNotConfigured(): void
    {
        $this->assertFalse(UniverseRewriteProbe::rewriteLooksConfigured(404));
        $this->assertFalse(UniverseRewriteProbe::rewriteLooksConfigured(0));
        $this->assertFalse(UniverseRewriteProbe::rewriteLooksConfigured(500));
        $this->assertFalse(UniverseRewriteProbe::rewriteLooksConfigured(308));
    }

    public function testFetchStatusUsesInjectedFetcher(): void
    {
        $seen = null;
        $code = UniverseRewriteProbe::fetchStatus(
            'https://example.test/uni1/',
            static function (string $url) use (&$seen): int {
                $seen = $url;
                return 200;
            }
        );

        $this->assertSame(200, $code);
        $this->assertSame('https://example.test/uni1/', $seen);
    }

    public function testFetchStatusCurlToClosedPortReturnsZero(): void
    {
        $code = UniverseRewriteProbe::fetchStatus('http://127.0.0.1:1/uni1/');
        $this->assertSame(0, $code);
    }
}
