<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for pure utility functions in GeneralFunctions.php.
 * Only functions with no database, filesystem, or global-state dependencies
 * are covered here.
 */
class GeneralFunctionsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // safe_unserialize
    // -------------------------------------------------------------------------

    public function testSafeUnserializeNullReturnsFalse(): void
    {
        $this->assertFalse(safe_unserialize(null));
    }

    public function testSafeUnserializeEmptyStringReturnsFalse(): void
    {
        $this->assertFalse(safe_unserialize(''));
    }

    public function testSafeUnserializeValidArrayRoundtrips(): void
    {
        $data = ['foo' => 'bar', 'n' => 42];
        $this->assertSame($data, safe_unserialize(serialize($data)));
    }

    public function testSafeUnserializeValidStringRoundtrips(): void
    {
        $this->assertSame('hello', safe_unserialize(serialize('hello')));
    }

    public function testSafeUnserializeValidIntRoundtrips(): void
    {
        $this->assertSame(123, safe_unserialize(serialize(123)));
    }

    public function testSafeUnserializeCorruptDataReturnsFalse(): void
    {
        // Suppress the E_NOTICE that unserialize() emits for malformed input
        $result = @safe_unserialize('not:valid:serialized:data');
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // floatToString
    // -------------------------------------------------------------------------

    public function testFloatToStringZeroDecimalsIsDefaultBehaviour(): void
    {
        $this->assertSame('42', floatToString(42));
    }

    public function testFloatToStringRoundsToRequestedPrecision(): void
    {
        $this->assertSame('3.14', floatToString(3.14159, 2));
    }

    public function testFloatToStringPadsTrailingZeros(): void
    {
        $this->assertSame('5.00', floatToString(5, 2));
    }

    public function testFloatToStringOutputModeReplacesCommaWithDot(): void
    {
        // output=true uses str_replace so the decimal separator is always '.'
        $this->assertSame('3.14', floatToString(3.14159, 2, true));
    }

    public function testFloatToStringHandlesNegativeNumbers(): void
    {
        $this->assertSame('-10', floatToString(-10));
    }

    // -------------------------------------------------------------------------
    // pretty_fly_time
    // -------------------------------------------------------------------------

    public function testPrettyFlyTimeZeroSeconds(): void
    {
        $this->assertSame('00:00:00', pretty_fly_time(0));
    }

    public function testPrettyFlyTimeExactOneHour(): void
    {
        $this->assertSame('01:00:00', pretty_fly_time(3600));
    }

    public function testPrettyFlyTimeMixedHoursMinutesSeconds(): void
    {
        // 1h 30m 45s = 3600 + 1800 + 45 = 5445
        $this->assertSame('01:30:45', pretty_fly_time(5445));
    }

    public function testPrettyFlyTimeMoreThan24Hours(): void
    {
        // 25 hours = 90000 s → hour field overflows past 24, no days shown
        $this->assertSame('25:00:00', pretty_fly_time(90000));
    }

    public function testPrettyFlyTimeOnlySeconds(): void
    {
        $this->assertSame('00:00:59', pretty_fly_time(59));
    }

    // -------------------------------------------------------------------------
    // isactiveDMExtra / DMExtra
    // -------------------------------------------------------------------------

    public function testIsactiveDMExtraReturnsTrueWhenExtraNotExpired(): void
    {
        // Extra expires at Time=100, current Time=50 → 50 - 100 = -50 ≤ 0 → active
        $this->assertTrue(isactiveDMExtra(100, 50));
    }

    public function testIsactiveDMExtraReturnsFalseWhenExtraExpired(): void
    {
        // Extra expires at Time=50, current Time=100 → 100 - 50 = 50 > 0 → not active
        $this->assertFalse(isactiveDMExtra(50, 100));
    }

    public function testIsactiveDMExtraReturnsTrueAtExactBoundary(): void
    {
        // Extra=100, Time=100 → 100 - 100 = 0 ≤ 0 → active
        $this->assertTrue(isactiveDMExtra(100, 100));
    }

    public function testDMExtraReturnsFirstValueWhenActive(): void
    {
        $this->assertSame('yes', DMExtra(100, 50, 'yes', 'no'));
    }

    public function testDMExtraReturnsSecondValueWhenExpired(): void
    {
        $this->assertSame('no', DMExtra(50, 100, 'yes', 'no'));
    }

    // -------------------------------------------------------------------------
    // isVacationMode / isInactive / isLongtermInactive
    // -------------------------------------------------------------------------

    public function testIsInactiveReturnsTrueWhenOnlinetimeIsOld(): void
    {
        $user = ['onlinetime' => TIMESTAMP - INACTIVE - 1];
        $this->assertTrue(isInactive($user));
    }

    public function testIsInactiveReturnsFalseForRecentlyActiveUser(): void
    {
        $user = ['onlinetime' => TIMESTAMP - 60];
        $this->assertFalse(isInactive($user));
    }

    public function testIsLongtermInactiveReturnsTrueWhenVeryOld(): void
    {
        $user = ['onlinetime' => TIMESTAMP - INACTIVE_LONG - 1];
        $this->assertTrue(isLongtermInactive($user));
    }

    public function testIsVacationModeReturnsTrueWhenFlagSetAndNotLongtermInactive(): void
    {
        $user = [
            'urlaubs_modus' => 1,
            'onlinetime'    => TIMESTAMP - 60,   // recent — not long-term inactive
        ];
        $this->assertTrue(isVacationMode($user));
    }

    public function testIsVacationModeReturnsFalseWhenLongtermInactive(): void
    {
        // Long-term inactive overrides vacation mode flag
        $user = [
            'urlaubs_modus' => 1,
            'onlinetime'    => TIMESTAMP - INACTIVE_LONG - 1,
        ];
        $this->assertFalse(isVacationMode($user));
    }

    // -------------------------------------------------------------------------
    // GetStartAddressLink / GetTargetAddressLink
    // -------------------------------------------------------------------------

    public function testGetStartAddressLinkContainsCoordinates(): void
    {
        $row = [
            'fleet_start_galaxy' => 2,
            'fleet_start_system' => 150,
            'fleet_start_planet' => 7,
        ];
        $link = GetStartAddressLink($row);
        $this->assertStringContainsString('[2:150:7]', $link);
        $this->assertStringContainsString('galaxy=2', $link);
        $this->assertStringContainsString('system=150', $link);
    }

    public function testGetTargetAddressLinkContainsCoordinates(): void
    {
        $row = [
            'fleet_end_galaxy' => 1,
            'fleet_end_system' => 300,
            'fleet_end_planet' => 12,
        ];
        $link = GetTargetAddressLink($row);
        $this->assertStringContainsString('[1:300:12]', $link);
    }

    // -------------------------------------------------------------------------
    // makebr
    // -------------------------------------------------------------------------

    public function testMakebrConvertsNewlinesToBrTags(): void
    {
        // nl2br inserts <br> before newlines but keeps the newline in the output
        $result = makebr("line1\nline2");
        $this->assertStringContainsString('<br>', $result);
        $this->assertStringContainsString('line1', $result);
        $this->assertStringContainsString('line2', $result);
    }

    // -------------------------------------------------------------------------
    // pretty_number
    // -------------------------------------------------------------------------

    public function testPrettyNumberFormatsThousandsSeparator(): void
    {
        // 1234567 → '1.234.567' (German dot-separator)
        $this->assertSame('1.234.567', pretty_number(1234567));
    }

    public function testPrettyNumberWithDecimalsUsesComma(): void
    {
        // 1234.5 with 2 decimals → '1.234,50'
        $this->assertSame('1.234,50', pretty_number(1234.5, 2));
    }

    public function testPrettyNumberZero(): void
    {
        $this->assertSame('0', pretty_number(0));
    }

    // -------------------------------------------------------------------------
    // BuildPlanetAddressLink
    // -------------------------------------------------------------------------

    public function testBuildPlanetAddressLinkContainsCoordinates(): void
    {
        $planet = ['galaxy' => 3, 'system' => 200, 'planet' => 9];
        $link   = BuildPlanetAddressLink($planet);
        $this->assertStringContainsString('[3:200:9]', $link);
        $this->assertStringContainsString('galaxy=3', $link);
        $this->assertStringContainsString('system=200', $link);
    }

    public function testBuildPlanetAddressLinkIsAnchorTag(): void
    {
        $planet = ['galaxy' => 1, 'system' => 1, 'planet' => 1];
        $link   = BuildPlanetAddressLink($planet);
        $this->assertStringStartsWith('<a ', $link);
        $this->assertStringContainsString('</a>', $link);
    }

    // -------------------------------------------------------------------------
    // pretty_time
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        // pretty_time() reads $LNG global for unit labels
        $GLOBALS['LNG'] = [
            'short_day'    => 'd',
            'short_hour'   => 'h',
            'short_minute' => 'm',
            'short_second' => 's',
        ];
    }

    public function testPrettyTimeFormatsHoursMinutesSeconds(): void
    {
        // 1h 2m 3s = 3723s, no days
        $this->assertSame('01h 02m 03s', pretty_time(3723));
    }

    public function testPrettyTimeShowsDayWhenAbove86400Seconds(): void
    {
        // 1 day exactly = 86400s
        $result = pretty_time(86400);
        $this->assertStringContainsString('1d', $result);
    }

    public function testPrettyTimeZeroSecondsShowsZeros(): void
    {
        $this->assertSame('00h 00m 00s', pretty_time(0));
    }

    public function testPrettyTimeDoesNotShowDayForUnder86400(): void
    {
        // 23h 59m 59s = 86399s — no day prefix
        $result = pretty_time(86399);
        $this->assertStringNotContainsString('d ', $result);
    }
}
