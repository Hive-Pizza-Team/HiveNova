<?php

use PHPUnit\Framework\TestCase;

class PlayerUtilTest extends TestCase
{
    // -------------------------------------------------------------------------
    // isHiveAccountValid
    // -------------------------------------------------------------------------

    /** @dataProvider validHiveAccountProvider */
    public function testIsHiveAccountValidAcceptsValidAccounts(string $account): void
    {
        $this->assertNotFalse(PlayerUtil::isHiveAccountValid($account), "Expected '$account' to be valid");
    }

    public function validHiveAccountProvider(): array
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
    public function testIsHiveAccountValidRejectsInvalidAccounts($account): void
    {
        $this->assertFalse((bool) PlayerUtil::isHiveAccountValid($account), "Expected '$account' to be invalid");
    }

    public function invalidHiveAccountProvider(): array
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

    // -------------------------------------------------------------------------
    // isMailValid
    // -------------------------------------------------------------------------

    /** @dataProvider validEmailProvider */
    public function testIsMailValidAcceptsValidAddresses(string $email): void
    {
        $this->assertNotFalse(PlayerUtil::isMailValid($email), "Expected '$email' to be valid");
    }

    public function validEmailProvider(): array
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

    public function invalidEmailProvider(): array
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
}
