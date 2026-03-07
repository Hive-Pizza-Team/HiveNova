<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for the Language class's ArrayAccess interface and addData merging.
 * These tests bypass filesystem/DB by constructing a Language in INSTALL mode
 * (MODE='INSTALL', DEFAULT_LANG='en' are defined in bootstrap.php) and
 * loading data exclusively via addData().
 */
class LanguageTest extends TestCase
{
    private Language $lng;

    protected function setUp(): void
    {
        $this->lng = new Language(null);
        $this->lng->addData([
            'greeting'   => 'Hello',
            'farewell'   => 'Goodbye',
            'nested_key' => ['a' => 1, 'b' => 2],
        ]);
    }

    // -------------------------------------------------------------------------
    // offsetExists / isset
    // -------------------------------------------------------------------------

    public function testOffsetExistsReturnsTrueForLoadedKey(): void
    {
        $this->assertTrue(isset($this->lng['greeting']));
    }

    public function testOffsetExistsReturnsFalseForMissingKey(): void
    {
        $this->assertFalse(isset($this->lng['no_such_key']));
    }

    // -------------------------------------------------------------------------
    // offsetGet
    // -------------------------------------------------------------------------

    public function testOffsetGetReturnsValueForKnownKey(): void
    {
        $this->assertSame('Hello', $this->lng['greeting']);
    }

    public function testOffsetGetReturnsKeyNameForMissingKey(): void
    {
        // By design Language::offsetGet falls back to the key itself when absent
        $this->assertSame('missing_key', $this->lng['missing_key']);
    }

    public function testOffsetGetReturnsNestedArray(): void
    {
        $this->assertSame(['a' => 1, 'b' => 2], $this->lng['nested_key']);
    }

    // -------------------------------------------------------------------------
    // offsetSet / offsetUnset
    // -------------------------------------------------------------------------

    public function testOffsetSetStoresValue(): void
    {
        $this->lng['new_entry'] = 'Hello World';
        $this->assertSame('Hello World', $this->lng['new_entry']);
    }

    public function testOffsetUnsetRemovesKey(): void
    {
        unset($this->lng['greeting']);
        $this->assertFalse(isset($this->lng['greeting']));
    }

    // -------------------------------------------------------------------------
    // addData merging
    // -------------------------------------------------------------------------

    public function testAddDataMergesNewKeys(): void
    {
        $this->lng->addData(['extra' => 'Extra value']);
        $this->assertSame('Extra value', $this->lng['extra']);
        // Original keys are preserved
        $this->assertSame('Goodbye', $this->lng['farewell']);
    }

    public function testAddDataOverwritesExistingKey(): void
    {
        $this->lng->addData(['greeting' => 'Hi']);
        $this->assertSame('Hi', $this->lng['greeting']);
    }

    public function testAddDataMergesNestedArraysRecursively(): void
    {
        $this->lng->addData(['nested_key' => ['b' => 99, 'c' => 3]]);
        $result = $this->lng['nested_key'];
        // 'a' comes from original, 'b' overwritten, 'c' added
        $this->assertSame(1, $result['a']);
        $this->assertSame(99, $result['b']);
        $this->assertSame(3, $result['c']);
    }

    // -------------------------------------------------------------------------
    // getLanguage
    // -------------------------------------------------------------------------

    public function testGetLanguageReturnsDefaultInInstallMode(): void
    {
        // Bootstrap defines MODE='INSTALL', DEFAULT_LANG='en'
        $this->assertSame('en', $this->lng->getLanguage());
    }
}
