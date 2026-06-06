<?php

use HiveNova\Auth\OpenIDAuth;
use HiveNova\Core\Database;
use HiveNova\Core\DatabaseInterface;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

/**
 * Minimal OpenID client double — avoids LightOpenID HTTP/discovery.
 */
class StubOpenIDClient
{
    public ?string $mode = 'id_res';

    public string $identity = 'https://openid.example/user';

    /** @var array<string, string> */
    private array $attributes = [];

    /** @param array<string, string> $attributes */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    /** @return array<string, string> */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function authUrl(): string
    {
        return 'https://openid.example/auth';
    }
}

/**
 * In-memory DB stub for OpenID auth queries only.
 */
class OpenIDAuthFakeDatabase implements DatabaseInterface
{
    /** @var array{validationID: int|string, validationKey: string, email?: string}|null */
    public ?array $pendingValidation = null;

    /** @var list<array<string, mixed>> */
    public array $loginRows = [];

    /** @var list<array<string, mixed>> */
    public array $insertLog = [];

    public function select($qry, array $params = [])
    {
        if (str_contains($qry, '%%USERS_AUTH%%') && str_contains($qry, 'INNER JOIN %%USERS%%')) {
            $email = (string) ($params[':email'] ?? '');
            $mode = (string) ($params[':mode'] ?? '');

            return array_values(array_filter(
                $this->loginRows,
                static fn (array $row): bool => ($row['email'] ?? '') === $email
                    && ($row['mode'] ?? '') === $mode,
            ));
        }

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

class OpenIDAuthTest extends TestCase
{
    use SwapDatabaseInstance;

    private OpenIDAuthFakeDatabase $fakeDb;

    /** @var array<string, mixed> */
    private array $savedServer = [];

    /** @var array<string, mixed> */
    private array $savedRequest = [];

    protected function setUp(): void
    {
        $this->defineHttpConstants();

        $this->fakeDb = new OpenIDAuthFakeDatabase();
        $this->swapDatabaseInstance($this->fakeDb);

        $this->savedServer = $_SERVER;
        $this->savedRequest = $_REQUEST;
        $_SERVER['REQUEST_URI'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_REQUEST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $this->restoreDatabaseInstance();
        $_SERVER = $this->savedServer;
        $_REQUEST = $this->savedRequest;

        parent::tearDown();
    }

    private function defineHttpConstants(): void
    {
        if (!defined('HTTPS')) {
            define('HTTPS', false);
        }
        if (!defined('HTTP_ROOT')) {
            define('HTTP_ROOT', '/');
        }
        if (!defined('HTTP_HOST')) {
            define('HTTP_HOST', '127.0.0.1');
        }
        if (!defined('PROTOCOL')) {
            define('PROTOCOL', 'http://');
        }
        if (!defined('HTTP_PATH')) {
            define('HTTP_PATH', PROTOCOL . HTTP_HOST . HTTP_ROOT);
        }
    }

    private function makeAuth(?StubOpenIDClient $client = null): OpenIDAuth
    {
        $auth = (new ReflectionClass(OpenIDAuth::class))->newInstanceWithoutConstructor();
        $client ??= new StubOpenIDClient();

        $prop = new ReflectionProperty(OpenIDAuth::class, 'oidObj');
        $prop->setAccessible(true);
        $prop->setValue($auth, $client);

        return $auth;
    }

    public function testIsActiveModeReturnsFalse(): void
    {
        $this->assertFalse($this->makeAuth()->isActiveMode());
    }

    public function testIsValidReturnsTrueWhenModePresentAndNotCancel(): void
    {
        $client = new StubOpenIDClient();
        $client->mode = 'id_res';

        $this->assertTrue($this->makeAuth($client)->isValid());
    }

    public function testIsValidReturnsFalseWhenModeIsCancel(): void
    {
        $client = new StubOpenIDClient();
        $client->mode = 'cancel';

        $this->assertFalse($this->makeAuth($client)->isValid());
    }

    public function testIsValidReturnsFalseWhenModeIsEmpty(): void
    {
        $client = new StubOpenIDClient();
        $client->mode = null;

        $this->assertFalse($this->makeAuth($client)->isValid());
    }

    public function testGetAccountPrefersEmail(): void
    {
        $client = new StubOpenIDClient();
        $client->setAttributes([
            'contact/email'       => 'player@example.com',
            'namePerson/friendly' => 'Friendly',
            'namePerson'          => 'Full Name',
        ]);

        $this->assertSame('player@example.com', $this->makeAuth($client)->getAccount());
    }

    public function testGetAccountFallsBackToFriendlyName(): void
    {
        $client = new StubOpenIDClient();
        $client->setAttributes([
            'namePerson/friendly' => 'FriendlyOnly',
        ]);

        $this->assertSame('FriendlyOnly', $this->makeAuth($client)->getAccount());
    }

    public function testGetAccountFallsBackToNamePerson(): void
    {
        $client = new StubOpenIDClient();
        $client->setAttributes([
            'namePerson' => 'PersonName',
        ]);

        $this->assertSame('PersonName', $this->makeAuth($client)->getAccount());
    }

    public function testRegisterInsertsAuthLinkWhenNoPendingValidation(): void
    {
        $client = new StubOpenIDClient();
        $client->identity = 'https://provider.example/id';
        $client->setAttributes([
            'contact/email' => 'new@example.com',
        ]);

        $this->makeAuth($client)->register();

        $this->assertCount(1, $this->fakeDb->insertLog);
        $this->assertSame('new@example.com', $this->fakeDb->insertLog[0][':email']);
        $this->assertSame('new@example.com', $this->fakeDb->insertLog[0][':accountId']);
        $this->assertSame('https://provider.example/id', $this->fakeDb->insertLog[0][':mode']);
    }

    public function testGetLoginDataReturnsMatchingUsers(): void
    {
        $client = new StubOpenIDClient();
        $client->identity = 'https://provider.example/id';
        $client->setAttributes([
            'contact/email' => 'login@example.com',
        ]);

        $this->fakeDb->loginRows = [[
            'email'      => 'login@example.com',
            'mode'       => 'https://provider.example/id',
            'id'         => 42,
            'username'   => 'player42',
            'dpath'      => 'avatar.gif',
            'authlevel'  => 0,
            'id_planet'  => 7,
        ]];

        $rows = $this->makeAuth($client)->getLoginData();

        $this->assertCount(1, $rows);
        $this->assertSame(42, (int) $rows[0]['id']);
        $this->assertSame('player42', $rows[0]['username']);
    }

    public function testGetAccountDataMapsOpenIdAttributes(): void
    {
        $client = new StubOpenIDClient();
        $client->setAttributes([
            'contact/email'       => 'data@example.com',
            'namePerson/friendly' => 'Display Name',
            'pref/language'       => 'en-US',
        ]);

        $data = $this->makeAuth($client)->getAccountData();

        $this->assertSame([
            'id'     => 'data@example.com',
            'name'   => 'data@example.com',
            'locale' => 'en-US',
        ], $data);
    }

    public function testConstructCompletesWhenOpenIdModePresent(): void
    {
        require_once ROOT_PATH . 'includes/libs/OpenID/openid.php';
        if (!class_exists('HiveNova\\Auth\\LightOpenID', false)) {
            class_alias(LightOpenID::class, 'HiveNova\\Auth\\LightOpenID');
        }

        $_GET['openid_mode'] = 'id_res';

        $auth = new OpenIDAuth();

        $this->assertInstanceOf(OpenIDAuth::class, $auth);
        $this->assertTrue($auth->isValid());
    }
}
