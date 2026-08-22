<?php

use HiveNova\Core\HiveMemo;

use PHPUnit\Framework\TestCase;

class HiveMemoTest extends TestCase
{
	private const FROM_WIF = '5J15npVK6qABGsbdsLnJdaF5esrEWxeejeE3KUx6r534ug4tyze';
	private const TO_WIF = '5K1gv5rEtHiACVTFq9ikhEijezMh4rkbbTPqu4CAGMnXcTLC1su';
	private const TO_PUB = 'STM8LbCRyqtXk5VKbdFwK1YBgiafqprAd7yysN49PnDwAsyoMqQME';

	public function testPlaintextWithoutHashIsUnchanged(): void
	{
		$this->assertSame('hello', HiveMemo::encode(self::FROM_WIF, self::TO_PUB, 'hello', 777));
	}

	public function testEncodeMatchesHiveTxVector(): void
	{
		$encoded = HiveMemo::encode(self::FROM_WIF, self::TO_PUB, '#avocado-banana-cherry-durian', 777);

		$this->assertSame(
			'#7w8Nh1ibxvZmdpmSmLTRBu5HsqjxYTMdeAw6haGqEhYCHbUegCroy4EBApJgB7MMazV8fPCrh92aQhTZk9LtoBCFGgS2XCPuDURRB9NLPNx74Y7uAdz7dvs3PFzRz2xqXfbmS5iBnGWk6AvgVfc8W26',
			$encoded
		);
	}

	public function testRecipientCanDecryptHiveTxVector(): void
	{
		$encoded = HiveMemo::encode(self::FROM_WIF, self::TO_PUB, '#avocado-banana-cherry-durian', 777);

		$this->assertSame('#avocado-banana-cherry-durian', HiveMemo::decode(self::TO_WIF, $encoded));
		$this->assertSame('#avocado-banana-cherry-durian', HiveMemo::decode(self::FROM_WIF, $encoded));
	}

	public function testEncodeOrEmptySwallowsFailure(): void
	{
		$this->assertSame('', HiveMemo::encodeOrEmpty('not-a-wif', self::TO_PUB, '#hi', 1));
	}

	public function testRandomNonceRoundTrip(): void
	{
		$encoded = HiveMemo::encode(self::FROM_WIF, self::TO_PUB, '#hello moon');
		$this->assertStringStartsWith('#', $encoded);
		$this->assertNotSame('#hello moon', $encoded);
		$this->assertSame('#hello moon', HiveMemo::decode(self::TO_WIF, $encoded));
	}
}
