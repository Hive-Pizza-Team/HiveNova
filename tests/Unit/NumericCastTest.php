<?php

use HiveNova\Core\NumericCast;
use PHPUnit\Framework\TestCase;

class NumericCastTest extends TestCase
{
	public function testToIntPassesThroughIntegers(): void
	{
		$this->assertSame(0, NumericCast::toInt(0));
		$this->assertSame(42, NumericCast::toInt(42));
		$this->assertSame(-7, NumericCast::toInt(-7));
		$this->assertSame(PHP_INT_MAX, NumericCast::toInt(PHP_INT_MAX));
		$this->assertSame(PHP_INT_MIN, NumericCast::toInt(PHP_INT_MIN));
	}

	public function testToIntTruncatesTowardZero(): void
	{
		$this->assertSame(12, NumericCast::toInt(12.9));
		$this->assertSame(-12, NumericCast::toInt(-12.9));
		$this->assertSame(1, NumericCast::toInt('1.9'));
	}

	public function testToIntTinyFloatIsZero(): void
	{
		$this->assertSame(0, NumericCast::toInt(1.4880000378734E-05));
		$this->assertSame(0, NumericCast::toInt(-1.4880000378734E-05));
		$this->assertSame(0, NumericCast::toInt(0.999));
	}

	public function testToIntClampsHugeFloats(): void
	{
		$this->assertSame(PHP_INT_MAX, NumericCast::toInt(1.9966429737744E+20));
		$this->assertSame(PHP_INT_MIN, NumericCast::toInt(-1.9966429737744E+20));
		$this->assertSame(PHP_INT_MAX, NumericCast::toInt((float) PHP_INT_MAX));
		$this->assertSame(PHP_INT_MAX, NumericCast::toInt((string) 1.9966429737744E+20));
	}

	public function testToIntNonFiniteAndGarbageAreZero(): void
	{
		$this->assertSame(0, NumericCast::toInt(NAN));
		$this->assertSame(0, NumericCast::toInt(INF));
		$this->assertSame(0, NumericCast::toInt(-INF));
		$this->assertSame(0, NumericCast::toInt('nope'));
		$this->assertSame(0, NumericCast::toInt(null));
		$this->assertSame(0, NumericCast::toInt([]));
	}

	public function testToIntBooleans(): void
	{
		$this->assertSame(1, NumericCast::toInt(true));
		$this->assertSame(0, NumericCast::toInt(false));
	}

	public function testToFiniteFloat(): void
	{
		$this->assertSame(0.0, NumericCast::toFiniteFloat(null));
		$this->assertSame(0.0, NumericCast::toFiniteFloat('x'));
		$this->assertSame(0.0, NumericCast::toFiniteFloat(NAN));
		$this->assertSame(0.0, NumericCast::toFiniteFloat(INF));
		$this->assertSame(0.0, NumericCast::toFiniteFloat(-INF));
		$this->assertSame(1.0, NumericCast::toFiniteFloat(true));
		$this->assertSame(0.0, NumericCast::toFiniteFloat(false));
		$this->assertEqualsWithDelta(1.4880000378734E-05, NumericCast::toFiniteFloat(1.4880000378734E-05), 1e-18);
		$this->assertSame(42.0, NumericCast::toFiniteFloat(42));
		$this->assertSame(1.5, NumericCast::toFiniteFloat('1.5'));
	}
}
