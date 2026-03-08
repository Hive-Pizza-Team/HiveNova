<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for HTTP::_GP — the request parameter reader.
 * $_REQUEST is manipulated directly since we're testing the parsing/casting
 * logic, not actual HTTP transport.
 */
class HTTPTest extends TestCase
{
    protected function tearDown(): void
    {
        // Restore superglobal after each test
        $_REQUEST = [];
    }

    // -------------------------------------------------------------------------
    // Missing key — returns default
    // -------------------------------------------------------------------------

    public function testGPReturnsIntDefaultWhenKeyAbsent(): void
    {
        $this->assertSame(0, HTTP::_GP('missing', 0));
    }

    public function testGPReturnsStringDefaultWhenKeyAbsent(): void
    {
        $this->assertSame('fallback', HTTP::_GP('missing', 'fallback'));
    }

    public function testGPReturnsFloatDefaultWhenKeyAbsent(): void
    {
        $this->assertSame(1.5, HTTP::_GP('missing', 1.5));
    }

    public function testGPReturnsArrayDefaultWhenKeyAbsent(): void
    {
        $this->assertSame([], HTTP::_GP('missing', []));
    }

    // -------------------------------------------------------------------------
    // Integer coercion
    // -------------------------------------------------------------------------

    public function testGPCastsToIntWhenDefaultIsInt(): void
    {
        $_REQUEST['level'] = '42';
        $this->assertSame(42, HTTP::_GP('level', 0));
    }

    public function testGPCastsStringToIntZeroForNonNumeric(): void
    {
        $_REQUEST['level'] = 'abc';
        $this->assertSame(0, HTTP::_GP('level', 0));
    }

    public function testGPCastsFloatStringToIntWhenDefaultIsInt(): void
    {
        $_REQUEST['level'] = '3.9';
        $this->assertSame(3, HTTP::_GP('level', 0));
    }

    // -------------------------------------------------------------------------
    // Float coercion
    // -------------------------------------------------------------------------

    public function testGPCastsToFloatWhenDefaultIsFloat(): void
    {
        $_REQUEST['ratio'] = '2.75';
        $this->assertSame(2.75, HTTP::_GP('ratio', 0.0));
    }

    public function testGPCastsIntStringToFloatWhenDefaultIsFloat(): void
    {
        $_REQUEST['ratio'] = '5';
        $this->assertSame(5.0, HTTP::_GP('ratio', 0.0));
    }

    // -------------------------------------------------------------------------
    // String — sanitisation
    // -------------------------------------------------------------------------

    public function testGPStripsHtmlSpecialCharsFromString(): void
    {
        $_REQUEST['name'] = '<script>alert(1)</script>';
        $result = HTTP::_GP('name', '');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testGPTrimesWhitespaceFromString(): void
    {
        $_REQUEST['name'] = '  hello  ';
        $this->assertSame('hello', HTTP::_GP('name', ''));
    }

    public function testGPNormalisesLineEndingsInString(): void
    {
        $_REQUEST['text'] = "line1\r\nline2";
        $result = HTTP::_GP('text', '');
        $this->assertStringNotContainsString("\r\n", $result);
        $this->assertStringContainsString("\n", $result);
    }

    public function testGPStripsNullBytesFromString(): void
    {
        $_REQUEST['name'] = "hello\0world";
        $result = HTTP::_GP('name', '');
        $this->assertStringNotContainsString("\0", $result);
    }

    // -------------------------------------------------------------------------
    // Array
    // -------------------------------------------------------------------------

    public function testGPReturnsArrayWhenDefaultIsArray(): void
    {
        $_REQUEST['ids'] = ['1', '2', '3'];
        $result = HTTP::_GP('ids', []);
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    public function testGPReturnsDefaultWhenRequestValueIsNotArrayButDefaultIs(): void
    {
        $_REQUEST['ids'] = 'not-an-array';
        $result = HTTP::_GP('ids', []);
        $this->assertSame([], $result);
    }

    public function testGPConvertsArrayValuesToIntWhenDefaultIsNumericArray(): void
    {
        // A default of [0] signals "cast values to int"
        $_REQUEST['ids'] = ['3', '7', '99'];
        $result = HTTP::_GP('ids', [0]);
        $this->assertSame([3, 7, 99], $result);
    }
}
