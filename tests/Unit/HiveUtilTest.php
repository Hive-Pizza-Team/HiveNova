<?php

use HiveNova\Core\HiveUtil;

use PHPUnit\Framework\TestCase;

class HiveUtilTest extends TestCase
{
    /** @dataProvider rpcErrorProvider */
    public function testIsRpcErrorDetectsRpcFailures(mixed $result, bool $expected): void
    {
        $this->assertSame($expected, HiveUtil::isRpcError($result));
    }

    public static function rpcErrorProvider(): array
    {
        return [
            'json-rpc error object' => [['code' => -32000, 'message' => 'Internal Error'], true],
            'account list result'   => [[['name' => 'alice', 'posting' => []]], false],
            'empty account list'    => [[], false],
            'null result'           => [null, true],
            'string result'         => ['error', true],
        ];
    }

    /** @dataProvider validHiveAccountProvider */
    public function testIsAccountValidAcceptsValidAccounts(string $account): void
    {
        $this->assertTrue(HiveUtil::isAccountValid($account), "Expected '$account' to be valid");
    }

    public static function validHiveAccountProvider(): array
    {
        return [
            'simple lowercase'        => ['tor'],
            'with hyphen'             => ['hive-nova'],
            'with numbers'            => ['player1'],
            'with dot separator'      => ['first.last'],
            'mixed alphanumeric'      => ['abc123'],
            'min length (3 chars)'    => ['abc'],
        ];
    }

    /** @dataProvider invalidHiveAccountProvider */
    public function testIsAccountValidRejectsInvalidAccounts($account): void
    {
        $this->assertFalse(HiveUtil::isAccountValid($account), "Expected '$account' to be invalid");
    }

    public static function invalidHiveAccountProvider(): array
    {
        return [
            'null value'              => [null],
            'empty string'            => [''],
            'too long (17 chars)'     => ['averylonghiveaccountname'],
            'starts with number'      => ['1player'],
            'starts with hyphen'      => ['-player'],
            'ends with hyphen'        => ['player-'],
            'uppercase letters'       => ['Player'],
            'contains space'          => ['hive nova'],
            'contains special chars'  => ['hive@nova'],
        ];
    }
}
