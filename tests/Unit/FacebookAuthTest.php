<?php

use HiveNova\Auth\FacebookAuth;
use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

/**
 * Facebook SDK double — no Graph API or redirect calls.
 */
class StubFacebookClient
{
    private int|string $user = 0;

    /** @var array<string, mixed> */
    private array $profile = [];

    public function setUser(int|string $user): void
    {
        $this->user = $user;
    }

    public function getUser(): int|string
    {
        return $this->user;
    }

    /** @param array<string, mixed> $profile */
    public function setProfile(array $profile): void
    {
        $this->profile = $profile;
    }

    /** @param array<string, mixed> $params */
    public function api(string $path, array $params = []): array
    {
        return $this->profile;
    }

    /** @param array<string, mixed> $params */
    public function getLoginUrl(array $params): string
    {
        return 'https://facebook.example/login?' . http_build_query($params);
    }

    public function getAccessToken(): string
    {
        return 'test-access-token';
    }
}

/**
 * In-memory DB stub for Facebook auth queries only.
 */
class FacebookAuthFakeDatabase implements DatabaseInterface
{
    /** @var array{validationID: int|string, validationKey: string, email?: string}|null */
    public ?array $pendingValidation = null;

    /** @var array<string, mixed>|false|null */
    public array|false|null $loginRow = null;

    /** @var list<array<string, mixed>> */
    public array $insertLog = [];

    public function select($qry, array $params = [])
    {
        return [];
    }

    public function selectSingle($qry, array $params = [], $field = false)
    {
        if (str_contains($qry, '%%USERS_VALID%%')) {
            $email = (string) ($params[':email'] ?? '');
            if ($this->pendingValidation !== null
                && ($this->pendingValidation['email'] ?? $email) === $email) {
                return $this->pendingValidation;
            }

            return false;
        }

        if (str_contains($qry, '%%USERS_AUTH%%') && str_contains($qry, 'INNER JOIN %%USERS%%')) {
            if ($this->loginRow === null) {
                return false;
            }

            $accountId = (string) ($params[':accountId'] ?? '');
            $mode = (string) ($params[':mode'] ?? '');

            if ((string) ($this->loginRow['account'] ?? '') === $accountId
                && ($this->loginRow['mode'] ?? '') === $mode) {
                return $this->loginRow;
            }

            return false;
        }

        return false;
    }

    public function insert($qry, array $params = [])
    {
        $this->insertLog[] = $params;

        return 1;
    }

    public function update($qry, array $params = []) {}

    public function delete($qry, array $params = []) {}

    public function replace($qry, array $params = []) {}

    public function query($qry) {}

    public function nativeQuery($qry) {}

    public function lastInsertId()
    {
        return 0;
    }

    public function rowCount()
    {
        return 0;
    }

    public function getQueryCounter()
    {
        return 0;
    }

    public function quote($str)
    {
        return (string) $str;
    }

    public function disconnect() {}

    public function beginTransaction(): void {}

    public function commit(): void {}

    public function rollback(): void {}
}

class FacebookAuthTest extends TestCase
{
    use SwapDatabaseInstance;

    private FacebookAuthFakeDatabase $fakeDb;

    /** @var array<string, mixed> */
    private array $savedGet = [];

    protected function setUp(): void
    {
        $this->resetConfigSingleton();

        if (!defined('HTTP_PATH')) {
            define('HTTP_PATH', 'http://127.0.0.1/');
        }

        $this->fakeDb = new FacebookAuthFakeDatabase();
        $this->swapDatabaseInstance($this->fakeDb);

        $this->savedGet = $_GET;
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $this->restoreDatabaseInstance();
        $this->resetConfigSingleton();
        $_GET = $this->savedGet;

        parent::tearDown();
    }

    private function resetConfigSingleton(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    private function setFacebookConfig(int $enabled): void
    {
        Config::setInstance(new Config([
            'fb_on'     => $enabled,
            'fb_apikey' => 'test-app-id',
            'fb_skey'   => 'test-secret',
            'uni'       => 1,
        ]));
    }

    private function makeAuth(?StubFacebookClient $client = null): FacebookAuth
    {
        $auth = (new ReflectionClass(FacebookAuth::class))->newInstanceWithoutConstructor();
        $client ??= new StubFacebookClient();

        $prop = new ReflectionProperty(FacebookAuth::class, 'fbObj');
        $prop->setAccessible(true);
        $prop->setValue($auth, $client);

        return $auth;
    }

    public function testIsActiveModeReturnsTrueWhenFacebookEnabled(): void
    {
        $this->setFacebookConfig(1);

        $this->assertTrue($this->makeAuth()->isActiveMode());
    }

    public function testIsActiveModeReturnsFalseWhenFacebookDisabled(): void
    {
        $this->setFacebookConfig(0);

        $this->assertFalse($this->makeAuth()->isActiveMode());
    }

    public function testConstructLeavesFacebookNullWhenInactive(): void
    {
        $this->setFacebookConfig(0);

        $auth = new FacebookAuth();

        $prop = new ReflectionProperty(FacebookAuth::class, 'fbObj');
        $prop->setAccessible(true);

        $this->assertNull($prop->getValue($auth));
    }

    public function testGetAccountReturnsFacebookUserId(): void
    {
        $client = new StubFacebookClient();
        $client->setUser(123456789);

        $this->assertSame(123456789, $this->makeAuth($client)->getAccount());
    }

    public function testIsValidReturnsUserIdWhenAlreadyAuthenticated(): void
    {
        $client = new StubFacebookClient();
        $client->setUser(987654321);

        $this->assertSame(987654321, $this->makeAuth($client)->isValid());
    }

    public function testRegisterInsertsAuthLinkWhenNoPendingValidation(): void
    {
        $client = new StubFacebookClient();
        $client->setUser(555);
        $client->setProfile([
            'email' => 'fb-user@example.com',
        ]);

        $this->makeAuth($client)->register();

        $this->assertCount(1, $this->fakeDb->insertLog);
        $this->assertSame('fb-user@example.com', $this->fakeDb->insertLog[0][':email']);
        $this->assertSame(555, $this->fakeDb->insertLog[0][':accountId']);
        $this->assertSame('facebook', $this->fakeDb->insertLog[0][':mode']);
    }

    public function testGetLoginDataReturnsLinkedUser(): void
    {
        $client = new StubFacebookClient();
        $client->setUser('fb-uid-99');

        $this->fakeDb->loginRow = [
            'account'    => 'fb-uid-99',
            'mode'       => 'facebook',
            'id'         => 15,
            'id_planet'  => 3,
        ];

        $row = $this->makeAuth($client)->getLoginData();

        $this->assertIsArray($row);
        $this->assertSame(15, (int) $row['id']);
        $this->assertSame(3, (int) $row['id_planet']);
    }

    public function testGetAccountDataReturnsFacebookProfileFields(): void
    {
        $client = new StubFacebookClient();
        $client->setUser(777);
        $client->setProfile([
            'id'     => 777,
            'name'   => 'Test Player',
            'locale' => 'en_GB',
        ]);

        $data = $this->makeAuth($client)->getAccountData();

        $this->assertSame([
            'id'     => 777,
            'name'   => 'Test Player',
            'locale' => 'en_GB',
        ], $data);
    }
}
