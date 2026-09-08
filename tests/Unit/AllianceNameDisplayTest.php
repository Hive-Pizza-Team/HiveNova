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

	public function testPlainStripsTagsFromItalicName(): void
	{
		$this->assertSame('Test', AllianceNameDisplay::plain('<i>Test</i>'));
	}

	public function testPlainStripsScriptTags(): void
	{
		$this->assertSame('x', AllianceNameDisplay::plain('<script>x</script>'));
	}

	public function testPlainEscapesQuotes(): void
	{
		$this->assertSame('O&#039;Brien &quot;Fleet&quot;', AllianceNameDisplay::plain('O\'Brien "Fleet"'));
	}
}
