<?php

use HiveNova\Core\AllianceNameDisplay;

use PHPUnit\Framework\TestCase;

class AllianceNameDisplayTest extends TestCase
{
	public function testPlainReturnsEmptyStringForNull(): void
	{
		$this->assertSame('', AllianceNameDisplay::plain(null));
	}

	public function testPlainReturnsEmptyStringForEmpty(): void
	{
		$this->assertSame('', AllianceNameDisplay::plain(''));
	}

	public function testPlainReturnsUnchangedPlainName(): void
	{
		$this->assertSame('Test', AllianceNameDisplay::plain('Test'));
	}

	public function testPlainEscapesRawTagsSoTheyShowAsText(): void
	{
		$this->assertSame('&lt;i&gt;Test&lt;/i&gt;', AllianceNameDisplay::plain('<i>Test</i>'));
	}

	public function testPlainDoesNotDoubleEscapeStoredEntities(): void
	{
		$this->assertSame('&lt;i&gt;Test&lt;/i&gt;', AllianceNameDisplay::plain('&lt;i&gt;Test&lt;/i&gt;'));
	}

	public function testPlainFlattensDoubleStoredEntities(): void
	{
		$this->assertSame(
			'&lt;i&gt;Test&lt;/i&gt;',
			AllianceNameDisplay::plain('&amp;lt;i&amp;gt;Test&amp;lt;/i&amp;gt;')
		);
	}

	public function testPlainEscapesScriptTagsWithoutExecuting(): void
	{
		$this->assertSame('&lt;script&gt;x&lt;/script&gt;', AllianceNameDisplay::plain('<script>x</script>'));
	}

	public function testPlainEscapesQuotes(): void
	{
		$this->assertSame('O&#039;Brien &quot;Fleet&quot;', AllianceNameDisplay::plain('O\'Brien "Fleet"'));
	}

	public function testPlainDoesNotDoubleEscapeStoredQuotes(): void
	{
		$this->assertSame(
			'O&#039;Brien &quot;Fleet&quot;',
			AllianceNameDisplay::plain('O&#039;Brien &quot;Fleet&quot;')
		);
	}

	public function testPlainEscapesAmpersandsOnce(): void
	{
		$this->assertSame('Tom &amp; Jerry', AllianceNameDisplay::plain('Tom & Jerry'));
		$this->assertSame('Tom &amp; Jerry', AllianceNameDisplay::plain('Tom &amp; Jerry'));
	}
}
