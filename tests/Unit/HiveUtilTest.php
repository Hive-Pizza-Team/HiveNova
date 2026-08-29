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

    public function testRpcErrorMessageReadsNestedDataMessage(): void
    {
        $message = HiveUtil::rpcErrorMessage([
            'code' => -32003,
            'message' => 'Assert Exception:account.has_mana',
            'data' => [
                'name' => 'plugin_exception',
                'message' => 'Account: moon.notify has 5044277700 RC, needs 9000000000 RC. Please wait to transact, or power up HIVE.',
            ],
        ]);

        $this->assertStringContainsString('has 5044277700 RC', $message);
        $this->assertTrue(HiveUtil::isResourceCreditError($message));
    }

    public function testIsResourceCreditErrorRejectsUnrelatedFailures(): void
    {
        $this->assertFalse(HiveUtil::isResourceCreditError('Internal Error'));
        $this->assertFalse(HiveUtil::isResourceCreditError('missing required active authority'));
        $this->assertFalse(HiveUtil::isResourceCreditError(''));
        $this->assertTrue(HiveUtil::isResourceCreditError('not enough resource credits to broadcast'));
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

    /** @dataProvider invalidHiveAccountExistsProvider */
    public function testAccountExistsReturnsFalseForInvalidAccounts($account): void
    {
        $this->assertFalse(HiveUtil::accountExists($account));
    }

    public static function invalidHiveAccountExistsProvider(): array
    {
        return [
            'null value'              => [null],
            'empty string'            => [''],
            'too long (17 chars)'     => ['averylonghiveaccountname'],
            'starts with number'      => ['1player'],
            'starts with hyphen'      => ['-player'],
            'ends with hyphen'        => ['player-'],
            'contains space'          => ['hive nova'],
            'contains special chars'  => ['hive@nova'],
        ];
    }

    public function testExtractProfileAboutPrefersPostingJsonMetadata(): void
    {
        $account = [
            'posting_json_metadata' => json_encode(['profile' => ['about' => '  Hive about  ']]),
            'json_metadata' => json_encode(['profile' => ['about' => 'legacy']]),
        ];

        $this->assertSame('Hive about', HiveUtil::extractProfileAbout($account));
    }

    public function testExtractProfileAboutFallsBackToJsonMetadata(): void
    {
        $account = [
            'posting_json_metadata' => '',
            'json_metadata' => json_encode(['profile' => ['about' => 'legacy about']]),
        ];

        $this->assertSame('legacy about', HiveUtil::extractProfileAbout($account));
    }

    public function testExtractProfileAboutReturnsEmptyWhenMissingOrInvalid(): void
    {
        $this->assertSame('', HiveUtil::extractProfileAbout(null));
        $this->assertSame('', HiveUtil::extractProfileAbout(['posting_json_metadata' => '{']));
        $this->assertSame('', HiveUtil::extractProfileAbout([
            'posting_json_metadata' => json_encode(['profile' => ['about' => '   ']]),
        ]));
    }

    public function testGetAccountAboutReturnsEmptyForInvalidAccount(): void
    {
        $this->assertSame('', HiveUtil::getAccountAbout('Not Valid!'));
    }

    public function testExtractMemoKeyAcceptsStmWif(): void
    {
        $key = 'STM8LbCRyqtXk5VKbdFwK1YBgiafqprAd7yysN49PnDwAsyoMqQME';
        $this->assertSame($key, HiveUtil::extractMemoKey(['memo_key' => $key]));
    }

    public function testExtractMemoKeyRejectsInvalidValues(): void
    {
        $this->assertSame('', HiveUtil::extractMemoKey(null));
        $this->assertSame('', HiveUtil::extractMemoKey(['memo_key' => '']));
        $this->assertSame('', HiveUtil::extractMemoKey(['memo_key' => 'not-a-key']));
        $this->assertSame('', HiveUtil::getMemoPublicKey('Not Valid!'));
    }

    public function testRpcNodesToTryCapsRetryBudget(): void
    {
        if (!defined('HIVE_RPC_NODES')) {
            define('HIVE_RPC_NODES', [
                'https://a.example',
                'https://b.example',
                'https://c.example',
                'https://d.example',
            ]);
        }

        $all = HiveUtil::getRpcNodes();
        $this->assertGreaterThan(3, count($all));
        $this->assertSame(array_slice($all, 0, 3), HiveUtil::rpcNodesToTry(3));
        $this->assertSame($all, HiveUtil::rpcNodesToTry(null));
        $this->assertCount(1, HiveUtil::rpcNodesToTry(0));
    }

    public function testInstallHiveClientErrorHandlerSwallowsDeprecations(): void
    {
        $previous = set_error_handler(static function (int $errno, string $errstr): bool {
            throw new ErrorException('outer:' . $errstr, 0, $errno);
        });

        try {
            $saw = HiveUtil::withHiveClient(function (): string {
                // Simulate mahdiyari Hive::__construct() overwriting the handler.
                set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
                    if (0 === error_reporting()) {
                        return false;
                    }
                    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
                });
                HiveUtil::installHiveClientErrorHandler();

                trigger_error('fake base58 nullable deprecation', E_USER_DEPRECATED);

                try {
                    trigger_error('real problem', E_USER_WARNING);
                    return 'should-not-reach';
                } catch (ErrorException $e) {
                    if ($e->getMessage() !== 'real problem') {
                        throw $e;
                    }
                }

                return 'ok';
            });
            $this->assertSame('ok', $saw);
        } finally {
            if ($previous !== null) {
                set_error_handler($previous);
            } else {
                restore_error_handler();
            }
        }
    }
}
