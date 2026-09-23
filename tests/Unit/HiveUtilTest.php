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

    public function testAccountsExistMarksInvalidNamesFalseWithoutRpc(): void
    {
        $this->assertSame(
            ['1player' => false, 'hive nova' => false],
            HiveUtil::accountsExist(['1player', 'Hive Nova', '1player'])
        );
        $this->assertSame([], HiveUtil::accountsExist(['', '  ']));
    }

    public function testExistingNamesFromAccountListReadsAccountNames(): void
    {
        $this->assertSame(
            ['alice', 'bob'],
            HiveUtil::existingNamesFromAccountList([
                ['name' => 'Alice'],
                'skip',
                ['name' => ' bob '],
            ])
        );
        $this->assertSame([], HiveUtil::existingNamesFromAccountList(['code' => 1, 'message' => 'err']));
        $this->assertSame([], HiveUtil::existingNamesFromAccountList(null));
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

    public function testStephenHillBase58IsPhp84SafeRelease(): void
    {
        // mahdiyari/hive-php requires ^1.1; we alias 2.1.0 (explicit nullables) as 1.1.5.
        // Reflection allowsNull() is also true for 1.1.5's implicit "ServiceInterface $service = null"
        // (Base58.php line 33), which PHP 8.4+ deprecates. Require the explicit signature in source.
        $version = \Composer\InstalledVersions::getPrettyVersion('stephenhill/base58');
        $this->assertNotFalse($version);
        $this->assertTrue(
            version_compare(ltrim((string) $version, 'v'), '2.1.0', '>='),
            "stephenhill/base58 must be 2.1+ (got {$version}) so Base58::__construct does not emit PHP 8.4 nullable deprecations"
        );

        $param = (new ReflectionMethod(StephenHill\Base58::class, '__construct'))->getParameters()[1];
        $type = $param->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $type);
        $this->assertTrue($type->allowsNull());
        $this->assertSame('StephenHill\\ServiceInterface', $type->getName());

        $source = (string) file_get_contents((new ReflectionClass(StephenHill\Base58::class))->getFileName());
        $this->assertMatchesRegularExpression(
            '/function __construct\(\s*\?string \$alphabet = null,\s*\?ServiceInterface \$service = null/s',
            $source
        );
    }

    public function testCallHiveRestoresPreviousErrorHandler(): void
    {
        if (!class_exists(\Hive\Hive::class)) {
            $this->markTestSkipped('hive-php is not installed');
        }

        $handler = static function (): bool {
            return true;
        };
        set_error_handler($handler);
        try {
            $ok = HiveUtil::callHive([], static function ($hive): bool {
                return $hive instanceof \Hive\Hive;
            });
            $this->assertTrue($ok);
            $this->assertSame($handler, $this->currentErrorHandler());
        } finally {
            restore_error_handler();
        }
    }

    public function testCallHiveRestoresHandlerWhenCallbackThrows(): void
    {
        if (!class_exists(\Hive\Hive::class)) {
            $this->markTestSkipped('hive-php is not installed');
        }

        $handler = static function (): bool {
            return true;
        };
        set_error_handler($handler);
        try {
            try {
                HiveUtil::callHive([], static function (): void {
                    throw new RuntimeException('hive failed');
                });
                $this->fail('expected exception');
            } catch (RuntimeException $e) {
                $this->assertSame('hive failed', $e->getMessage());
            }
            $this->assertSame($handler, $this->currentErrorHandler());
        } finally {
            restore_error_handler();
        }
    }

    public function testRpcCallRestoresErrorHandlerWhenNodeFails(): void
    {
        if (!class_exists(\Hive\Hive::class)) {
            $this->markTestSkipped('hive-php is not installed');
        }
        if (!defined('HIVE_RPC_TIMEOUT')) {
            define('HIVE_RPC_TIMEOUT', 1);
        }

        $handler = static function (): bool {
            return true;
        };
        $previous = HiveUtil::$rpcNodesOverride;
        HiveUtil::$rpcNodesOverride = ['http://127.0.0.1:9'];
        set_error_handler($handler);
        try {
            $result = HiveUtil::rpcCall('condenser_api.get_accounts', '[["alice"]]', 1);
            $this->assertNull($result);
            $this->assertSame($handler, $this->currentErrorHandler());
        } finally {
            HiveUtil::$rpcNodesOverride = $previous;
            restore_error_handler();
        }
    }

    public function testPublicKeyFromStringDecodesWithoutReplacingErrorHandler(): void
    {
        if (!extension_loaded('gmp') && !extension_loaded('bcmath')) {
            $this->markTestSkipped('gmp or bcmath required to decode Base58');
        }

        $handler = static function (int $errno): bool {
            if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
                return true;
            }
            throw new ErrorException('unexpected diagnostic', 0, $errno);
        };
        set_error_handler($handler);
        try {
            $key = HiveUtil::publicKeyFromString('STM8LbCRyqtXk5VKbdFwK1YBgiafqprAd7yysN49PnDwAsyoMqQME');
            $this->assertNotNull($key);
            $this->assertSame($handler, $this->currentErrorHandler());
        } finally {
            restore_error_handler();
        }
    }

    public function testPublicKeyFromStringReturnsNullForUndecodableKey(): void
    {
        $handler = static function (): bool {
            return true;
        };
        set_error_handler($handler);
        try {
            $this->assertNull(HiveUtil::publicKeyFromString('STM0not-a-base58-key!!!'));
            $this->assertSame($handler, $this->currentErrorHandler());
        } finally {
            restore_error_handler();
        }
    }

    public function testPostingSignatureMatchesRejectsMalformedAccountWithoutThrowing(): void
    {
        $handler = static function (int $errno, string $message): bool {
            throw new ErrorException($message, 0, $errno);
        };
        set_error_handler($handler);
        try {
            $this->assertFalse(HiveUtil::postingSignatureMatches('alice', 'sig', null));
            $this->assertFalse(HiveUtil::postingSignatureMatches('alice', 'sig', []));
            $this->assertFalse(HiveUtil::postingSignatureMatches('alice', 'sig', [['name' => 'alice']]));
            $this->assertFalse(HiveUtil::postingSignatureMatches('alice', 'sig', [['posting' => 'nope']]));
            $this->assertFalse(HiveUtil::postingSignatureMatches('alice', 'sig', [['posting' => ['key_auths' => []]]]));
            $this->assertFalse(HiveUtil::postingSignatureMatches('alice', 'sig', [['posting' => ['key_auths' => [['STM0not-a-base58-key!!!', 1]]]]]));
            $this->assertSame($handler, $this->currentErrorHandler());
        } finally {
            restore_error_handler();
        }
    }

    public function testPostingSignatureMatchesRejectsBadSignatureWithoutThrowing(): void
    {
        if (!extension_loaded('gmp') && !extension_loaded('bcmath')) {
            $this->markTestSkipped('gmp or bcmath required to decode Base58');
        }

        $account = [[
            'posting' => [
                'key_auths' => [
                    ['STM8LbCRyqtXk5VKbdFwK1YBgiafqprAd7yysN49PnDwAsyoMqQME', 1],
                ],
            ],
        ]];
        $handler = static function (int $errno): bool {
            if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
                return true;
            }
            throw new ErrorException('unexpected diagnostic', 0, $errno);
        };
        set_error_handler($handler);
        try {
            $this->assertFalse(HiveUtil::postingSignatureMatches('alice', str_repeat('ab', 65), $account));
            $this->assertSame($handler, $this->currentErrorHandler());
        } finally {
            restore_error_handler();
        }
    }

    public function testIsSignValidReturnsFalseWhenRpcFails(): void
    {
        if (!class_exists(\Hive\Hive::class)) {
            $this->markTestSkipped('hive-php is not installed');
        }
        if (!defined('HIVE_RPC_TIMEOUT')) {
            define('HIVE_RPC_TIMEOUT', 1);
        }

        $previous = HiveUtil::$rpcNodesOverride;
        HiveUtil::$rpcNodesOverride = ['http://127.0.0.1:9'];
        $handler = static function (): bool {
            return true;
        };
        set_error_handler($handler);
        try {
            $this->assertFalse(HiveUtil::isSignValid('not valid', str_repeat('a', 40)));
            $this->assertFalse(HiveUtil::isSignValid('alice', str_repeat('ab', 40)));
            $this->assertSame($handler, $this->currentErrorHandler());
        } finally {
            HiveUtil::$rpcNodesOverride = $previous;
            restore_error_handler();
        }
    }

    private function currentErrorHandler(): ?callable
    {
        $current = set_error_handler(static function (): bool {
            return true;
        });
        restore_error_handler();

        return $current;
    }
}
