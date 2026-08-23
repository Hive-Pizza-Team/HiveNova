<?php

use PHPUnit\Framework\TestCase;

class MessageWriteFormTest extends TestCase
{
	public function testComposeFormPostsToSendEndpoint(): void
	{
		$tpl = file_get_contents(dirname(__DIR__, 2) . '/styles/templates/game/page.messages.write.tpl');
		$this->assertNotFalse($tpl);
		$this->assertMatchesRegularExpression('/<form[^>]*\bmethod="post"/', $tpl);
		$this->assertStringContainsString('page=messages', $tpl);
		$this->assertStringContainsString('mode=send', $tpl);
		$this->assertDoesNotMatchRegularExpression('/<form[^>]*\baction=""/', $tpl);
	}

	public function testWritePageLoadsMessageWriteScriptWithJsSuffix(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/includes/pages/game/ShowMessagesPage.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString("loadscript('message-write.js')", $src);
	}
}
