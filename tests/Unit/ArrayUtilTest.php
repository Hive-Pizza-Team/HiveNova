<?php

use PHPUnit\Framework\TestCase;

class ArrayUtilTest extends TestCase
{
    // -------------------------------------------------------------------------
    // combineArrayWithSingleElement
    // -------------------------------------------------------------------------

    public function testCombineWithSingleElementMapsAllKeysToSameValue(): void
    {
        $result = ArrayUtil::combineArrayWithSingleElement(['a', 'b', 'c'], 0);
        $this->assertSame(['a' => 0, 'b' => 0, 'c' => 0], $result);
    }

    public function testCombineWithSingleElementWorksWithStringValue(): void
    {
        $result = ArrayUtil::combineArrayWithSingleElement(['metal', 'crystal'], 'none');
        $this->assertSame(['metal' => 'none', 'crystal' => 'none'], $result);
    }

    public function testCombineWithSingleElementReturnsEmptyArrayForEmptyKeys(): void
    {
        $result = ArrayUtil::combineArrayWithSingleElement([], 42);
        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // combineArrayWithKeyElements
    // -------------------------------------------------------------------------

    public function testCombineWithKeyElementsExtractsMatchingValues(): void
    {
        $keys = ['metal', 'crystal'];
        $var  = ['metal' => 500, 'crystal' => 200, 'deuterium' => 100];

        $result = ArrayUtil::combineArrayWithKeyElements($keys, $var);
        $this->assertSame(['metal' => 500, 'crystal' => 200], $result);
    }

    public function testCombineWithKeyElementsFallsBackToKeyNameWhenMissing(): void
    {
        $keys = ['metal', 'missing_resource'];
        $var  = ['metal' => 500];

        $result = ArrayUtil::combineArrayWithKeyElements($keys, $var);
        $this->assertSame(['metal' => 500, 'missing_resource' => 'missing_resource'], $result);
    }

    public function testCombineWithKeyElementsReturnsEmptyArrayForEmptyKeys(): void
    {
        $result = ArrayUtil::combineArrayWithKeyElements([], ['metal' => 500]);
        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // arrayKeyExistsRecursive
    // -------------------------------------------------------------------------

    public function testRecursiveKeyExistsFindsTopLevelKey(): void
    {
        $this->assertTrue(ArrayUtil::arrayKeyExistsRecursive('metal', ['metal' => 500]));
    }

    public function testRecursiveKeyExistsFindsNestedKey(): void
    {
        $data = ['resources' => ['metal' => 500, 'crystal' => 200]];
        $this->assertTrue(ArrayUtil::arrayKeyExistsRecursive('crystal', $data));
    }

    public function testRecursiveKeyExistsFindsDeeplyNestedKey(): void
    {
        $data = ['a' => ['b' => ['c' => ['target' => 1]]]];
        $this->assertTrue(ArrayUtil::arrayKeyExistsRecursive('target', $data));
    }

    public function testRecursiveKeyExistsReturnsFalseWhenAbsent(): void
    {
        $data = ['metal' => 500, 'nested' => ['crystal' => 200]];
        $this->assertFalse(ArrayUtil::arrayKeyExistsRecursive('deuterium', $data));
    }

    public function testRecursiveKeyExistsReturnsFalseOnEmptyArray(): void
    {
        $this->assertFalse(ArrayUtil::arrayKeyExistsRecursive('anything', []));
    }
}
