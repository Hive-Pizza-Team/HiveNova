<?php

use HiveNova\Core\Language;

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

    // -------------------------------------------------------------------------
    // offsetSet with null offset (append)
    // -------------------------------------------------------------------------

    public function testOffsetSetWithNullOffsetAppendsValue(): void
    {
        $this->lng[] = 'appended';
        // Value was appended; check it exists somewhere in a fresh loop
        $this->assertSame('appended', $this->lng[0]);
    }

    // -------------------------------------------------------------------------
    // addData edge cases
    // -------------------------------------------------------------------------

    public function testAddDataWithEmptyArrayLeavesDataUnchanged(): void
    {
        $before = $this->lng['greeting'];
        $this->lng->addData([]);
        $this->assertSame($before, $this->lng['greeting']);
    }

    public function testAddDataCanAddNumericKeys(): void
    {
        $this->lng->addData([42 => 'forty-two']);
        $this->assertSame('forty-two', $this->lng[42]);
    }

    public function testMultipleAddDataCallsAccumulate(): void
    {
        $this->lng->addData(['key1' => 'val1']);
        $this->lng->addData(['key2' => 'val2']);
        $this->assertSame('val1', $this->lng['key1']);
        $this->assertSame('val2', $this->lng['key2']);
        // Original data still intact
        $this->assertSame('Hello', $this->lng['greeting']);
    }

    // -------------------------------------------------------------------------
    // getTemplate — missing file returns sentinel string
    // -------------------------------------------------------------------------

    public function testGetTemplateReturnsSentinelForMissingTemplate(): void
    {
        $result = $this->lng->getTemplate('nonexistent_template_xyz');
        $this->assertStringContainsString('nonexistent_template_xyz', $result);
        $this->assertStringContainsString('not found', $result);
    }

    // -------------------------------------------------------------------------
    // offsetGet fallback mirrors key name
    // -------------------------------------------------------------------------

    public function testOffsetGetFallbackReturnsSameKeyForAnyMissingKey(): void
    {
        $this->assertSame('some_random_key_abc', $this->lng['some_random_key_abc']);
        $this->assertSame('another_missing', $this->lng['another_missing']);
    }

    // -------------------------------------------------------------------------
    // Round-trip: set then unset then check missing fallback
    // -------------------------------------------------------------------------

    public function testAfterUnsetOffsetGetReturnsFallback(): void
    {
        $this->lng['temp'] = 'value';
        $this->assertSame('value', $this->lng['temp']);
        unset($this->lng['temp']);
        // After unset, offsetGet should return key name as fallback
        $this->assertSame('temp', $this->lng['temp']);
    }
}
