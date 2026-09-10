<?php

use HiveNova\Core\PublicContactService;

use PHPUnit\Framework\TestCase;

class PublicContactServiceTest extends TestCase
{
	public function testEmptyConfigFallsBackToDiscordAndNotice(): void
	{
		$contact = PublicContactService::resolve([
			'address'        => '',
			'phone'          => '',
			'mail'           => '',
			'notice'         => '',
			'discordUrl'     => 'https://discord.gg/bP6ksCeEUk',
			'noticeFallback' => 'Community support is available on Discord.',
		]);

		$this->assertTrue($contact['hasContent']);
		$this->assertSame('https://discord.gg/bP6ksCeEUk', $contact['discordUrl']);
		$this->assertSame('Community support is available on Discord.', $contact['notice']);
		$this->assertSame('', $contact['address']);
		$this->assertSame('', $contact['phone']);
		$this->assertSame('', $contact['mail']);
		$this->assertSame('', $contact['mailHref']);
	}

	public function testAdminFieldsArePreserved(): void
	{
		$contact = PublicContactService::resolve([
			'address'    => "Moon HQ\nOrbit",
			'phone'      => '555-0100',
			'mail'       => 'support@example.test',
			'notice'     => 'Custom legal notice',
			'discordUrl' => 'https://discord.gg/bP6ksCeEUk',
		]);

		$this->assertSame("Moon HQ\nOrbit", $contact['address']);
		$this->assertSame('555-0100', $contact['phone']);
		$this->assertSame('support@example.test', $contact['mail']);
		$this->assertSame('mailto:support@example.test', $contact['mailHref']);
		$this->assertSame('Custom legal notice', $contact['notice']);
		$this->assertTrue($contact['hasContent']);
	}

	public function testMailHrefKeepsAbsoluteAndMailtoUrls(): void
	{
		$this->assertSame('https://hive.pizza/support', PublicContactService::mailHref('https://hive.pizza/support'));
		$this->assertSame('mailto:ops@example.test', PublicContactService::mailHref('mailto:ops@example.test'));
		$this->assertSame('', PublicContactService::mailHref('   '));
	}

	public function testDefaultDiscordWhenUrlOmitted(): void
	{
		$contact = PublicContactService::resolve([]);
		$this->assertSame(PublicContactService::DEFAULT_DISCORD_URL, $contact['discordUrl']);
		$this->assertTrue($contact['hasContent']);
	}

	public function testWhitespaceOnlyFieldsTreatedAsEmpty(): void
	{
		$contact = PublicContactService::resolve([
			'address' => '  ',
			'phone'   => "\t",
			'mail'    => null,
			'notice'  => '   ',
		]);

		$this->assertSame('', $contact['address']);
		$this->assertSame('', $contact['phone']);
		$this->assertSame('', $contact['mail']);
		$this->assertSame(PublicContactService::DEFAULT_NOTICE_FALLBACK, $contact['notice']);
	}
}
