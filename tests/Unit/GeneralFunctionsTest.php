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
}
