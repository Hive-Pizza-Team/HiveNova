<?php

use HiveNova\Core\Cache\TeamspeakBuildCache;
use HiveNova\Core\Config;

use PHPUnit\Framework\TestCase;

class TeamspeakCytsStubState
{
    public static bool $connectResult = true;

    /** @var array<string, int> */
    public static array $serverInfo = [
        'server_currentusers' => 7,
        'server_maxusers' => 32,
    ];

    /** @var list<string> */
    public static array $debugErrors = ['connection refused'];

    public static bool $disconnectCalled = false;

    public static function reset(): void
    {
        self::$connectResult = true;
        self::$serverInfo = [
            'server_currentusers' => 7,
            'server_maxusers' => 32,
        ];
        self::$debugErrors = ['connection refused'];
        self::$disconnectCalled = false;
    }
}

class TeamspeakTs3AdminStubState
{
    /** @var array{success: bool, errors: list<string>} */
    public static array $connectResult = ['success' => true, 'errors' => []];

    /** @var array{success: bool, errors: list<string>} */
    public static array $selectResult = ['success' => true, 'errors' => []];

    /** @var array{success: bool, errors: list<string>} */
    public static array $loginResult = ['success' => true, 'errors' => []];

    /** @var array{success: bool, errors: list<string>, data: array<string, int|string>} */
    public static array $serverInfoResult = [
        'success' => true,
        'errors' => [],
        'data' => [
            'virtualserver_password' => 'secret',
            'virtualserver_clientsonline' => 10,
            'virtualserver_maxclients' => 50,
        ],
    ];

    public static bool $logoutCalled = false;

    public static function reset(): void
    {
        self::$connectResult = ['success' => true, 'errors' => []];
        self::$selectResult = ['success' => true, 'errors' => []];
        self::$loginResult = ['success' => true, 'errors' => []];
        self::$serverInfoResult = [
            'success' => true,
            'errors' => [],
            'data' => [
                'virtualserver_password' => 'secret',
                'virtualserver_clientsonline' => 10,
                'virtualserver_maxclients' => 50,
            ],
        ];
        self::$logoutCalled = false;
    }
}

class TeamspeakBuildCacheTest extends TestCase
{
    private static string $originalCwd;

    private static string $stubRoot;

    protected function setUp(): void
    {
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        TeamspeakCytsStubState::reset();
        TeamspeakTs3AdminStubState::reset();
    }

    public static function setUpBeforeClass(): void
    {
        self::$originalCwd = getcwd() ?: ROOT_PATH;
        self::$stubRoot = sys_get_temp_dir() . '/hivenova-teamspeak-stub-' . getmypid();

        $cytsDir = self::$stubRoot . '/includes/libs/teamspeak/cyts';
        $ts3Dir = self::$stubRoot . '/includes/libs/teamspeak/ts3admin';
        mkdir($cytsDir, 0777, true);
        mkdir($ts3Dir, 0777, true);

        file_put_contents($cytsDir . '/cyts.class.php', <<<'PHP'
<?php

namespace HiveNova\Core\Cache;

class cyts
{
    public function connect($sIP, $sTCP, $sUDP = false, $sTimeout = 3)
    {
        return \TeamspeakCytsStubState::$connectResult;
    }

    public function info_serverInfo()
    {
        return \TeamspeakCytsStubState::$serverInfo;
    }

    public function disconnect()
    {
        \TeamspeakCytsStubState::$disconnectCalled = true;
    }

    public function debug()
    {
        return \TeamspeakCytsStubState::$debugErrors;
    }
}
PHP);

        file_put_contents($ts3Dir . '/ts3admin.class.php', <<<'PHP'
<?php

namespace HiveNova\Core\Cache;

class ts3admin
{
    public function __construct($host, $port, $timeout)
    {
    }

    public function connect()
    {
        return \TeamspeakTs3AdminStubState::$connectResult;
    }

    public function selectServer($value, $type = 'port', $virtual = false)
    {
        return \TeamspeakTs3AdminStubState::$selectResult;
    }

    public function login($username, $password)
    {
        return \TeamspeakTs3AdminStubState::$loginResult;
    }

    public function serverInfo()
    {
        return \TeamspeakTs3AdminStubState::$serverInfoResult;
    }

    public function logout()
    {
        \TeamspeakTs3AdminStubState::$logoutCalled = true;
    }
}
PHP);
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$originalCwd)) {
            chdir(self::$originalCwd);
        }

        if (isset(self::$stubRoot) && is_dir(self::$stubRoot)) {
            self::removeDirectory(self::$stubRoot);
        }
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function setTeamspeakConfig(array $overrides = []): void
    {
        Config::setInstance(new Config(array_merge([
            'uni' => 1,
            'ts_version' => 0,
            'ts_server' => '127.0.0.1',
            'ts_tcpport' => 10011,
            'ts_udpport' => 9987,
            'ts_timeout' => 2,
            'ts_login' => 'serveradmin',
            'ts_password' => 'query-password',
        ], $overrides)), 1);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withStubIncludes(callable $callback)
    {
        chdir(self::$stubRoot);

        try {
            return $callback();
        } finally {
            chdir(self::$originalCwd);
        }
    }

    private static function removeDirectory(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    public function testBuildCacheReturnsEmptyArrayForUnsupportedVersion(): void
    {
        $this->setTeamspeakConfig(['ts_version' => 1]);

        $result = (new TeamspeakBuildCache())->buildCache();

        $this->assertSame([], $result);
    }

    public function testBuildCacheTs2SuccessReturnsServerInfo(): void
    {
        $this->setTeamspeakConfig(['ts_version' => 2]);

        $result = $this->withStubIncludes(static function () {
            return (new TeamspeakBuildCache())->buildCache();
        });

        $this->assertSame([
            'password' => '',
            'current' => 7,
            'maxuser' => 32,
        ], $result);
        $this->assertTrue(TeamspeakCytsStubState::$disconnectCalled);
    }

    public function testBuildCacheTs2ConnectFailureThrowsException(): void
    {
        $this->setTeamspeakConfig(['ts_version' => 2]);
        TeamspeakCytsStubState::$connectResult = false;
        TeamspeakCytsStubState::$debugErrors = ['timeout', 'host unreachable'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Teamspeak-Error: timeout<br>' . "\r\n" . 'host unreachable');

        $this->withStubIncludes(static function () {
            return (new TeamspeakBuildCache())->buildCache();
        });
    }

    public function testBuildCacheTs3SuccessReturnsServerInfo(): void
    {
        $this->setTeamspeakConfig(['ts_version' => 3]);

        $result = $this->withStubIncludes(static function () {
            return (new TeamspeakBuildCache())->buildCache();
        });

        $this->assertSame([
            'password' => 'secret',
            'current' => 9,
            'maxuser' => 50,
        ], $result);
        $this->assertTrue(TeamspeakTs3AdminStubState::$logoutCalled);
    }

    public function testBuildCacheTs3ConnectFailureThrowsException(): void
    {
        $this->setTeamspeakConfig(['ts_version' => 3]);
        TeamspeakTs3AdminStubState::$connectResult = [
            'success' => false,
            'errors' => ['failed to connect'],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Teamspeak-Error: failed to connect');

        $this->withStubIncludes(static function () {
            return (new TeamspeakBuildCache())->buildCache();
        });
    }

    public function testBuildCacheTs3SelectServerFailureThrowsException(): void
    {
        $this->setTeamspeakConfig(['ts_version' => 3]);
        TeamspeakTs3AdminStubState::$selectResult = [
            'success' => false,
            'errors' => ['invalid virtual server'],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Teamspeak-Error: invalid virtual server');

        $this->withStubIncludes(static function () {
            return (new TeamspeakBuildCache())->buildCache();
        });
    }

    public function testBuildCacheTs3LoginFailureThrowsException(): void
    {
        $this->setTeamspeakConfig(['ts_version' => 3]);
        TeamspeakTs3AdminStubState::$loginResult = [
            'success' => false,
            'errors' => ['bad login name or password'],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Teamspeak-Error: bad login name or password');

        $this->withStubIncludes(static function () {
            return (new TeamspeakBuildCache())->buildCache();
        });
    }

    public function testBuildCacheTs3ServerInfoFailureThrowsException(): void
    {
        $this->setTeamspeakConfig(['ts_version' => 3]);
        TeamspeakTs3AdminStubState::$serverInfoResult = [
            'success' => false,
            'errors' => ['insufficient permissions'],
            'data' => [],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Teamspeak-Error: insufficient permissions');

        $this->withStubIncludes(static function () {
            return (new TeamspeakBuildCache())->buildCache();
        });
    }
}
