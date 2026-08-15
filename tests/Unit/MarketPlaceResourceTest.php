<?php

use HiveNova\Core\MarketPlaceResource;
use PHPUnit\Framework\TestCase;

class MarketPlaceResourceTest extends TestCase
{
    public function testTechIdMapsMetalCrystalDeuterium(): void
    {
        $this->assertSame(901, MarketPlaceResource::techId(1));
        $this->assertSame(902, MarketPlaceResource::techId(2));
        $this->assertSame(903, MarketPlaceResource::techId(3));
        $this->assertNull(MarketPlaceResource::techId(0));
        $this->assertNull(MarketPlaceResource::techId(4));
    }

    public function testLabelUsesTechLanguageKeys(): void
    {
        $tech = [901 => 'Metal', 902 => 'Crystal', 903 => 'Deuterium'];
        $this->assertSame('Metal', MarketPlaceResource::label(1, $tech));
        $this->assertSame('Crystal', MarketPlaceResource::label(2, $tech));
        $this->assertSame('', MarketPlaceResource::label(9, $tech));
    }

    public function testAmountsPutsValueOnMatchingResource(): void
    {
        $this->assertSame([901 => 50, 902 => 0, 903 => 0], MarketPlaceResource::amounts(1, 50));
        $this->assertSame([901 => 0, 902 => 12, 903 => 0], MarketPlaceResource::amounts(2, 12));
        $this->assertSame([901 => 0, 902 => 0, 903 => 7], MarketPlaceResource::amounts(3, 7));
        $this->assertSame([901 => 0, 902 => 0, 903 => 0], MarketPlaceResource::amounts(0, 99));
    }

    public function testHistoryLimitUsesConstantAndCaps(): void
    {
        $this->assertSame(MARKET_TRADE_HISTORY_LIMIT, MarketPlaceResource::historyLimit());
        $this->assertSame(1, MarketPlaceResource::historyLimit(0));
        $this->assertSame(200, MarketPlaceResource::historyLimit(999));
        $this->assertSame(25, MarketPlaceResource::historyLimit(25));
    }
}
