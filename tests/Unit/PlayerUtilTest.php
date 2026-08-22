<?php

use HiveNova\Core\Config;
use HiveNova\Core\PlayerUtil;

use PHPUnit\Framework\TestCase;

class PlayerUtilTest extends TestCase
{
    // -------------------------------------------------------------------------
    // isMailValid
    // -------------------------------------------------------------------------

    /** @dataProvider validEmailProvider */
    public function testIsMailValidAcceptsValidAddresses(string $email): void
    {
        $this->assertNotFalse(PlayerUtil::isMailValid($email), "Expected '$email' to be valid");
    }

    public static function validEmailProvider(): array
    {
        return [
            'simple'           => ['user@example.com'],
            'with subdomain'   => ['user@mail.example.com'],
            'with plus'        => ['user+tag@example.com'],
            'with dots'        => ['first.last@example.org'],
            'numeric local'    => ['123@example.com'],
        ];
    }

    /** @dataProvider invalidEmailProvider */
    public function testIsMailValidRejectsInvalidAddresses(string $email): void
    {
        $this->assertFalse(PlayerUtil::isMailValid($email), "Expected '$email' to be invalid");
    }

    public static function invalidEmailProvider(): array
    {
        return [
            'missing @'        => ['userexample.com'],
            'missing domain'   => ['user@'],
            'missing local'    => ['@example.com'],
            'double @'         => ['user@@example.com'],
            'empty string'     => [''],
        ];
    }

    // -------------------------------------------------------------------------
    // isNameValid
    // -------------------------------------------------------------------------

    public function testIsNameValidAcceptsAlphanumericAndAllowedSymbols(): void
    {
        $this->assertNotFalse(PlayerUtil::isNameValid('Player One'));
        $this->assertNotFalse(PlayerUtil::isNameValid('Dark-Lord'));
        $this->assertNotFalse(PlayerUtil::isNameValid('Tor.Nova'));
        $this->assertNotFalse(PlayerUtil::isNameValid('x_99'));
    }

    public function testIsNameValidRejectsDisallowedCharacters(): void
    {
        // isNameValid is a character-set check (letters, numbers, _ - . space only)
        $this->assertFalse((bool) PlayerUtil::isNameValid('<script>'));
        $this->assertFalse((bool) PlayerUtil::isNameValid('hack@er'));
        $this->assertFalse((bool) PlayerUtil::isNameValid('user/path'));
        $this->assertFalse((bool) PlayerUtil::isNameValid('user!'));
    }

    // -------------------------------------------------------------------------
    // cryptPassword
    // -------------------------------------------------------------------------

    public function testCryptPasswordReturnsBcryptHash(): void
    {
        $hash = PlayerUtil::cryptPassword('secret');
        $this->assertStringStartsWith('$2y$', $hash);
    }

    public function testCryptPasswordVerifiesWithOriginalPassword(): void
    {
        $hash = PlayerUtil::cryptPassword('mypassword');
        $this->assertTrue(password_verify('mypassword', $hash));
    }

    public function testCryptPasswordWrongPasswordFails(): void
    {
        $hash = PlayerUtil::cryptPassword('correct');
        $this->assertFalse(password_verify('wrong', $hash));
    }

    public function testCryptPasswordProducesDifferentHashEachCall(): void
    {
        // bcrypt salts are random — two hashes of the same input must differ
        $this->assertNotSame(
            PlayerUtil::cryptPassword('same'),
            PlayerUtil::cryptPassword('same')
        );
    }

    // -------------------------------------------------------------------------
    // getPlayerAvatarURL
    // -------------------------------------------------------------------------

    public function testGetPlayerAvatarURLReturnsHiveUrlWhenAccountMatches(): void
    {
        $user = ['username' => 'alice', 'hive_account' => 'alice'];
        $this->assertStringContainsString('images.hive.blog/u/alice/avatar', PlayerUtil::getPlayerAvatarURL($user));
    }

    public function testGetPlayerAvatarURLReturnsFallbackImageWhenNoAccount(): void
    {
        $user = ['username' => 'alice', 'hive_account' => ''];
        $this->assertSame('styles/resource/images/user.png', PlayerUtil::getPlayerAvatarURL($user));
    }

    public function testGetPlayerAvatarURLReturnsFallbackWhenAccountMismatch(): void
    {
        $user = ['username' => 'alice', 'hive_account' => 'bob'];
        $this->assertSame('styles/resource/images/user.png', PlayerUtil::getPlayerAvatarURL($user));
    }

    public function testGetPlayerAvatarURLIsCaseInsensitiveForUsername(): void
    {
        // username is lowercased before comparison
        $user = ['username' => 'Alice', 'hive_account' => 'alice'];
        $this->assertStringContainsString('images.hive.blog/u/alice/avatar', PlayerUtil::getPlayerAvatarURL($user));
    }

    // -------------------------------------------------------------------------
    // getPlayerBadges
    // -------------------------------------------------------------------------

    public function testGetPlayerBadgesReturnsHiveLinkWhenAccountMatches(): void
    {
        $user = ['username' => 'alice', 'hive_account' => 'alice'];
        $this->assertStringContainsString('peakd.com/@alice', PlayerUtil::getPlayerBadges($user));
    }

    public function testGetPlayerBadgesReturnsLinkIconWhenAccountDiffersButNotEmpty(): void
    {
        $user = ['username' => 'alice', 'hive_account' => 'other'];
        $this->assertSame('🔗', PlayerUtil::getPlayerBadges($user));
    }

    public function testGetPlayerBadgesReturnsBrokenChainWhenNoHiveAccount(): void
    {
        $user = ['username' => 'alice', 'hive_account' => ''];
        $this->assertSame('⛓️‍💥', PlayerUtil::getPlayerBadges($user));
    }

    // -------------------------------------------------------------------------
    // public profile message
    // -------------------------------------------------------------------------

    public function testIsHiveIdentityPublicWhenUsernameMatchesHiveAccount(): void
    {
        $this->assertTrue(PlayerUtil::isHiveIdentityPublic([
            'username' => 'Alice',
            'hive_account' => 'alice',
        ]));
        $this->assertFalse(PlayerUtil::isHiveIdentityPublic([
            'username' => 'alice',
            'hive_account' => 'bob',
        ]));
        $this->assertFalse(PlayerUtil::isHiveIdentityPublic([
            'username' => 'alice',
            'hive_account' => '',
        ]));
    }

    public function testResolvePublicMessageUsesStoredTextWhenHiveLinkIsNotPublic(): void
    {
        $user = [
            'username' => 'alice',
            'hive_account' => 'bob',
            'public_message' => 'Trade deuterium',
        ];

        $this->assertSame('Trade deuterium', PlayerUtil::resolvePublicMessage($user, static function (): string {
            return 'should not be used';
        }));
    }

    public function testResolvePublicMessagePrefersStoredTextOverHiveAbout(): void
    {
        $user = [
            'username' => 'alice',
            'hive_account' => 'alice',
            'public_message' => 'in-game message',
        ];
        $fetched = false;

        $this->assertSame('in-game message', PlayerUtil::resolvePublicMessage($user, static function () use (&$fetched): string {
            $fetched = true;
            return 'Hive bio';
        }));
        $this->assertFalse($fetched, 'Hive about must not be fetched when the profile message is set');
    }

    public function testResolvePublicMessageUsesHiveAboutWhenStoredIsEmpty(): void
    {
        $user = [
            'username' => 'alice',
            'hive_account' => 'alice',
            'public_message' => '',
        ];

        $this->assertSame('Hive bio', PlayerUtil::resolvePublicMessage($user, static function (string $account): string {
            TestCase::assertSame('alice', $account);
            return 'Hive bio';
        }));
    }

    public function testResolvePublicMessageIsEmptyWhenNeitherSourceHasText(): void
    {
        $user = [
            'username' => 'alice',
            'hive_account' => 'alice',
            'public_message' => '',
        ];

        $this->assertSame('', PlayerUtil::resolvePublicMessage($user, static function (): string {
            return '';
        }));
    }

    public function testResolvePublicMessageIsEmptyWhenStoredBlankAndHiveIsNotPublic(): void
    {
        $user = [
            'username' => 'alice',
            'hive_account' => 'bob',
            'public_message' => '   ',
        ];
        $fetched = false;

        $this->assertSame('', PlayerUtil::resolvePublicMessage($user, static function () use (&$fetched): string {
            $fetched = true;
            return 'Hive bio';
        }));
        $this->assertFalse($fetched);
    }

    public function testSanitizePublicMessageStripsImgAndTruncates(): void
    {
        $this->assertSame('evilhello', PlayerUtil::sanitizePublicMessage("  [img]evil.php[/img]hello  "));
        $this->assertSame(
            PlayerUtil::PUBLIC_MESSAGE_MAX_LENGTH,
            strlen(PlayerUtil::sanitizePublicMessage(str_repeat('a', 2500)))
        );
    }

    public function testSanitizePublicMessageDecodesRepeatedHtmlEntities(): void
    {
        $this->assertSame("It's", PlayerUtil::sanitizePublicMessage("It's"));
        $this->assertSame("It's", PlayerUtil::sanitizePublicMessage("It&#039;s"));
        $this->assertSame("It's", PlayerUtil::sanitizePublicMessage("It&amp;#039;s"));
        $this->assertSame("It's", PlayerUtil::sanitizePublicMessage("It&amp;amp;#039;s"));
    }

    public function testPublicMessageFromRequestSkipsHttpGpQuoting(): void
    {
        $this->assertSame("It's mine", PlayerUtil::publicMessageFromRequest([
            'publicMessage' => "It's mine",
        ]));
        $this->assertSame("It's mine", PlayerUtil::publicMessageFromRequest([
            'publicMessage' => "It&#039;s mine",
        ]));
        $this->assertSame('', PlayerUtil::publicMessageFromRequest([]));
        $this->assertSame('', PlayerUtil::publicMessageFromRequest(['publicMessage' => ['x']]));
        $this->assertSame('', PlayerUtil::publicMessageFromRequest(['publicMessage' => "\x80"]));
    }

    public function testPublicMessageFromRequestUsesRequestSuperglobal(): void
    {
        $_REQUEST['publicMessage'] = "It&#039;s";
        try {
            $this->assertSame("It's", PlayerUtil::publicMessageFromRequest());
        } finally {
            unset($_REQUEST['publicMessage']);
        }
    }

    public function testFormatPublicMessageHtmlEscapesAndKeepsLineBreaks(): void
    {
        $this->assertSame('', PlayerUtil::formatPublicMessageHtml(''));
        $this->assertSame("a&lt;b&gt;<br>\nc", PlayerUtil::formatPublicMessageHtml("a<b>\nc"));
    }

    public function testFormatPublicMessageHtmlDecodesStoredApostrophesOnce(): void
    {
        $this->assertSame("It&#039;s", PlayerUtil::formatPublicMessageHtml("It's"));
        $this->assertSame("It&#039;s", PlayerUtil::formatPublicMessageHtml("It&#039;s"));
        $this->assertSame("It&#039;s", PlayerUtil::formatPublicMessageHtml("It&amp;#039;s"));
    }

    // -------------------------------------------------------------------------
    // checkPosition — uses Config (no DB)
    // -------------------------------------------------------------------------

    private function makePositionConfig(array $overrides = []): Config
    {
        return new Config(array_merge([
            'uni'         => 1,
            'max_galaxy'  => 5,
            'max_system'  => 499,
            'max_planets' => 15,
        ], $overrides));
    }

    protected function setUp(): void
    {
        // Reset Config singleton between tests
        $ref = new ReflectionProperty(Config::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    public function testCheckPositionReturnsTrueForValidCoords(): void
    {
        Config::setInstance($this->makePositionConfig(), 1);
        $this->assertTrue(PlayerUtil::checkPosition(1, 3, 250, 8));
    }

    public function testCheckPositionReturnsFalseForGalaxyZero(): void
    {
        Config::setInstance($this->makePositionConfig(), 1);
        $this->assertFalse(PlayerUtil::checkPosition(1, 0, 1, 1));
    }

    public function testCheckPositionReturnsFalseForGalaxyExceedingMax(): void
    {
        Config::setInstance($this->makePositionConfig(), 1);
        $this->assertFalse(PlayerUtil::checkPosition(1, 6, 1, 1));
    }

    public function testCheckPositionReturnsFalseForSystemZero(): void
    {
        Config::setInstance($this->makePositionConfig(), 1);
        $this->assertFalse(PlayerUtil::checkPosition(1, 1, 0, 1));
    }

    public function testCheckPositionReturnsFalseForSystemExceedingMax(): void
    {
        Config::setInstance($this->makePositionConfig(), 1);
        $this->assertFalse(PlayerUtil::checkPosition(1, 1, 500, 1));
    }

    public function testCheckPositionReturnsFalseForPositionZero(): void
    {
        Config::setInstance($this->makePositionConfig(), 1);
        $this->assertFalse(PlayerUtil::checkPosition(1, 1, 1, 0));
    }

    public function testCheckPositionReturnsFalseForPositionExceedingMax(): void
    {
        Config::setInstance($this->makePositionConfig(), 1);
        $this->assertFalse(PlayerUtil::checkPosition(1, 1, 1, 16));
    }

    public function testCheckPositionAcceptsBoundaryValues(): void
    {
        Config::setInstance($this->makePositionConfig(), 1);
        // Exactly at max values — should be valid
        $this->assertTrue(PlayerUtil::checkPosition(1, 5, 499, 15));
        // Exactly at minimum values — should be valid
        $this->assertTrue(PlayerUtil::checkPosition(1, 1, 1, 1));
    }

    // -------------------------------------------------------------------------
    // maxPlanetCount — pure math over Config values
    // -------------------------------------------------------------------------

    private function makePlanetCountConfig(array $overrides = []): Config
    {
        return new Config(array_merge([
            'uni'               => 1,
            'min_player_planets'=> 1,
            'planets_tech'      => 4,
            'planets_officier'  => 2,
            'planets_per_tech'  => 1,
        ], $overrides));
    }

    public function testMaxPlanetCountBaselineWithNoTechOrBonus(): void
    {
        Config::setInstance($this->makePlanetCountConfig(), 1);
        $GLOBALS['resource'][124] = 'astrophysics_tech';
        $USER = [
            'universe'          => 1,
            'astrophysics_tech' => 0,
            'factor'            => ['Planets' => 0],
        ];
        // min_player_planets=1, tech=0*1=0 (capped at planets_tech=4), bonus=0
        $this->assertSame(1, PlayerUtil::maxPlanetCount($USER));
    }

    public function testMaxPlanetCountScalesWithAstrophysicsLevel(): void
    {
        Config::setInstance($this->makePlanetCountConfig(), 1);
        $GLOBALS['resource'][124] = 'astrophysics_tech';
        $USER = [
            'universe'          => 1,
            'astrophysics_tech' => 3,
            'factor'            => ['Planets' => 0],
        ];
        // min=1, tech=min(4, 3*1)=3, bonus=0 → ceil(1+3+0)=4
        $this->assertSame(4, PlayerUtil::maxPlanetCount($USER));
    }

    public function testMaxPlanetCountCapsTechBonus(): void
    {
        Config::setInstance($this->makePlanetCountConfig(), 1);
        $GLOBALS['resource'][124] = 'astrophysics_tech';
        $USER = [
            'universe'          => 1,
            'astrophysics_tech' => 100,  // way above cap of 4
            'factor'            => ['Planets' => 0],
        ];
        // min=1, tech capped at planets_tech=4, bonus=0 → ceil(5)=5
        $this->assertSame(5, PlayerUtil::maxPlanetCount($USER));
    }

    public function testMaxPlanetCountIncludesOfficerBonus(): void
    {
        Config::setInstance($this->makePlanetCountConfig(), 1);
        $GLOBALS['resource'][124] = 'astrophysics_tech';
        $USER = [
            'universe'          => 1,
            'astrophysics_tech' => 2,
            'factor'            => ['Planets' => 2],
        ];
        // min=1, tech=min(4,2)=2, bonus=min(2,2)=2 → ceil(5)=5
        $this->assertSame(5, PlayerUtil::maxPlanetCount($USER));
    }

    public function testMaxPlanetCountUnlimitedTechWhenMinPlanetsDisabled(): void
    {
        Config::setInstance($this->makePlanetCountConfig([
            'min_player_planets' => 0,
            'planets_tech'       => 4,
            'planets_officier'   => 2,
        ]), 1);
        $GLOBALS['resource'][124] = 'astrophysics_tech';
        $USER = [
            'universe'          => 1,
            'astrophysics_tech' => 10,
            'factor'            => ['Planets' => 5],
        ];
        // min=0, tech cap 999 → 10, bonus cap 999 → 5 → ceil(15)=15
        $this->assertSame(15, PlayerUtil::maxPlanetCount($USER));
    }
}
