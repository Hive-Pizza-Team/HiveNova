<?php

use PHPUnit\Framework\TestCase;

class CalculateMIPAttackTest extends TestCase
{
    public function test_returns_empty_when_all_missiles_intercepted(): void
    {
        $result = calculateMIPAttack(0, 0, 10, [401 => 5], 401, 10);
        $this->assertSame([], $result);
    }

    public function test_destroys_defenses_in_order(): void
    {
        $GLOBALS['pricelist'][401]['cost'] = [901 => 2000, 902 => 0];
        $GLOBALS['pricelist'][402]['cost'] = [901 => 1500, 902 => 0];
        $GLOBALS['CombatCaps'][503]['attack'] = 12000;

        $destroyed = calculateMIPAttack(0, 0, 100, [401 => 3, 402 => 2], 401, 0);

        $this->assertArrayHasKey(401, $destroyed);
        $this->assertGreaterThan(0, $destroyed[401]);
    }
}
