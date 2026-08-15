<?php

use HiveNova\Core\StatBanner;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class StatBannerFakeDatabase extends FakeDatabase
{
    /** @var array<string, mixed>|null */
    public ?array $bannerRow = null;

    /** @var list<array{query: string, params: array<string, mixed>}> */
    public array $selectSingleCalls = [];

    public function selectSingle($qry, array $params = [], $field = false)
    {
        $this->selectSingleCalls[] = ['query' => $qry, 'params' => $params];

        if (str_contains($qry, '%%STATPOINTS%%')
            && str_contains($qry, '%%PLANETS%%')
            && str_contains($qry, 'config.ttf_file')) {
            if ($this->bannerRow === null) {
                return $field === false ? null : false;
            }

            if ($field !== false) {
                return $this->bannerRow[$field] ?? false;
            }

            return $this->bannerRow;
        }

        return parent::selectSingle($qry, $params, $field);
    }
}

class StatBannerTest extends TestCase
{
    use SwapDatabaseInstance;

    private StatBannerFakeDatabase $fake;

    private mixed $savedGetDebug = null;

    protected function setUp(): void
    {
        $this->fake = new StatBannerFakeDatabase();
        $this->swapDatabaseInstance($this->fake);
        $this->bootstrapLanguage();

        if (array_key_exists('debug', $_GET)) {
            $this->savedGetDebug = $_GET['debug'];
        }
    }

    protected function tearDown(): void
    {
        if ($this->savedGetDebug === null) {
            unset($_GET['debug']);
        } else {
            $_GET['debug'] = $this->savedGetDebug;
        }

        unset($GLOBALS['LNG']);
        $this->restoreDatabaseInstance();
        parent::tearDown();
    }

    private function bootstrapLanguage(): void
    {
        $LNG = [];
        require ROOT_PATH . 'language/en/BANNER.php';
        $GLOBALS['LNG'] = $LNG;
    }

    private function gdAvailable(): bool
    {
        return extension_loaded('gd');
    }

    private function bannerJpgPath(): string
    {
        return 'styles/resource/images/banner.jpg';
    }

    private function defaultTtfPath(): ?string
    {
        $path = ROOT_PATH . 'styles/resource/fonts/DroidSansMono.ttf';

        return file_exists($path) ? $path : null;
    }

    private function bannerAssetsAvailable(): bool
    {
        return $this->gdAvailable()
            && file_exists($this->bannerJpgPath())
            && $this->defaultTtfPath() !== null;
    }

    /** @return array<string, mixed> */
    private function sampleBannerData(array $overrides = []): array
    {
        return array_merge([
            'username' => 'Commander',
            'wons' => 7,
            'loos' => 3,
            'draws' => 0,
            'total_points' => 125000,
            'total_rank' => 42,
            'name' => 'Homeworld',
            'galaxy' => 1,
            'system' => 2,
            'planet' => 3,
            'game_name' => 'HiveNova',
            'users_amount' => 500,
            'ttf_file' => $this->defaultTtfPath() ?? '/tmp/missing-font.ttf',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // GetData
    // -------------------------------------------------------------------------

    public function testGetDataReturnsJoinedBannerRow(): void
    {
        $row = $this->sampleBannerData(['ttf_file' => '/fonts/test.ttf']);
        $this->fake->bannerRow = $row;

        $banner = new StatBanner();
        $result = $banner->GetData(7);

        $this->assertSame($row, $result);
    }

    public function testGetDataReturnsNullWhenRowMissing(): void
    {
        $this->fake->bannerRow = null;

        $banner = new StatBanner();
        $this->assertNull($banner->GetData(99));
    }

    public function testGetDataPassesUserIdAndStatTypeToDatabase(): void
    {
        $this->fake->bannerRow = $this->sampleBannerData();

        $banner = new StatBanner();
        $banner->GetData(15);

        $this->assertCount(1, $this->fake->selectSingleCalls);
        $call = $this->fake->selectSingleCalls[0];
        $this->assertStringContainsString('%%STATPOINTS%%', $call['query']);
        $this->assertStringContainsString('config.ttf_file', $call['query']);
        $this->assertSame(15, $call['params'][':userId']);
        $this->assertSame(1, $call['params'][':statType']);
    }

    // -------------------------------------------------------------------------
    // CreateUTF8Banner
    // -------------------------------------------------------------------------

    public function testCreateUTF8BannerOutputsValidJpegWhenAssetsPresent(): void
    {
        if (!$this->bannerAssetsAvailable()) {
            $this->markTestSkipped('GD extension, banner.jpg, and a TTF font are required.');
        }

        $_GET['debug'] = 1;

        $banner = new StatBanner();

        ob_start();
        $banner->CreateUTF8Banner($this->sampleBannerData());
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        $this->assertSame("\xFF\xD8", substr($output, 0, 2));
    }

    public function testCreateUTF8BannerHandlesZeroTotalFights(): void
    {
        if (!$this->bannerAssetsAvailable()) {
            $this->markTestSkipped('GD extension, banner.jpg, and a TTF font are required.');
        }

        $_GET['debug'] = 1;

        $banner = new StatBanner();
        $data = $this->sampleBannerData([
            'wons' => 0,
            'loos' => 0,
            'draws' => 0,
        ]);

        ob_start();
        $banner->CreateUTF8Banner($data);
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        $this->assertSame("\xFF\xD8", substr($output, 0, 2));
    }

    public function testCreateUTF8BannerSkipsGracefullyWhenGdMissing(): void
    {
        if ($this->gdAvailable()) {
            $this->markTestSkipped('Only meaningful when the GD extension is absent.');
        }

        $this->expectException(Error::class);

        $banner = new StatBanner();
        $banner->CreateUTF8Banner($this->sampleBannerData());
    }

    public function testCreateUTF8BannerMissingFontShowsErrorBanner(): void
    {
        if (!$this->gdAvailable() || !file_exists($this->bannerJpgPath())) {
            $this->markTestSkipped('GD extension and banner.jpg are required.');
        }

        $output = $this->runStatBannerSubprocess(
            'require ROOT_PATH . "includes/GeneralFunctions.php";'
            . ' $LNG = [];'
            . ' require ROOT_PATH . "language/en/BANNER.php";'
            . ' $GLOBALS["LNG"] = $LNG;'
            . ' $_GET["debug"] = 1;'
            . ' $banner = new HiveNova\Core\StatBanner();'
            . ' $banner->CreateUTF8Banner(['
            . ' "username" => "Commander",'
            . ' "wons" => 1,'
            . ' "loos" => 0,'
            . ' "draws" => 0,'
            . ' "total_points" => 100,'
            . ' "total_rank" => 1,'
            . ' "game_name" => "HiveNova",'
            . ' "ttf_file" => ROOT_PATH . "styles/resource/fonts/does-not-exist.ttf",'
            . ' ]);'
        );

        $this->assertNotEmpty($output);
        $this->assertSame("\xFF\xD8", substr($output, 0, 2));
    }

    // -------------------------------------------------------------------------
    // BannerError
    // -------------------------------------------------------------------------

    public function testBannerErrorOutputsJpegWithMessage(): void
    {
        if (!$this->gdAvailable()) {
            $this->markTestSkipped('GD extension is required.');
        }

        $output = $this->runStatBannerSubprocess(
            '$banner = new HiveNova\Core\StatBanner();'
            . ' $banner->BannerError("TTF Font missing!");'
        );

        $this->assertNotEmpty($output);
        $this->assertSame("\xFF\xD8", substr($output, 0, 2));
    }

    private function runStatBannerSubprocess(string $body): string
    {
        $script = sprintf(
            "define('ROOT_PATH', %s); chdir(ROOT_PATH); require ROOT_PATH . 'vendor/autoload.php'; %s",
            var_export(ROOT_PATH, true),
            $body
        );
        $output = shell_exec('php -r ' . escapeshellarg($script));

        $this->assertIsString($output, 'StatBanner subprocess did not produce output.');

        return $output;
    }
}
