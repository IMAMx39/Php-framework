<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use Framework\Security\CsrfTokenManager;
use Framework\Session\Session;
use PHPUnit\Framework\TestCase;

/**
 * Tests de CsrfTokenManager avec une session mockée.
 *
 * @runTestsInSeparateProcesses
 */
class CsrfTokenManagerTest extends TestCase
{
    private Session $session;
    private CsrfTokenManager $manager;

    protected function setUp(): void
    {
        $this->session = $this->createMock(Session::class);
        $this->manager = new CsrfTokenManager($this->session);
    }

    // ------------------------------------------------------------------
    // getToken
    // ------------------------------------------------------------------

    public function testGetTokenGeneratesAndStoresNewTokenWhenAbsent(): void
    {
        $this->session
            ->method('has')
            ->with(CsrfTokenManager::SESSION_KEY)
            ->willReturn(false);

        $this->session
            ->expects($this->once())
            ->method('set')
            ->with(CsrfTokenManager::SESSION_KEY, $this->matchesRegularExpression('/^[0-9a-f]{64}$/'));

        $this->session
            ->method('get')
            ->with(CsrfTokenManager::SESSION_KEY)
            ->willReturn('aabbcc' . str_repeat('0', 58));

        $token = $this->manager->getToken();

        $this->assertNotEmpty($token);
    }

    public function testGetTokenReturnsExistingTokenWhenPresent(): void
    {
        $existing = str_repeat('a', 64);

        $this->session->method('has')->willReturn(true);
        $this->session->method('get')->willReturn($existing);

        // set() ne doit PAS être appelé
        $this->session->expects($this->never())->method('set');

        $this->assertSame($existing, $this->manager->getToken());
    }

    // ------------------------------------------------------------------
    // refresh
    // ------------------------------------------------------------------

    public function testRefreshGeneratesNewToken(): void
    {
        $this->session
            ->expects($this->once())
            ->method('set')
            ->with(
                CsrfTokenManager::SESSION_KEY,
                $this->matchesRegularExpression('/^[0-9a-f]{64}$/'),
            );

        $token = $this->manager->refresh();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testRefreshReturnsDifferentTokensEachTime(): void
    {
        $tokens = [];

        $this->session->method('set')->willReturnCallback(
            function (string $key, string $value) use (&$tokens) {
                $tokens[] = $value;
            }
        );

        $t1 = $this->manager->refresh();
        $t2 = $this->manager->refresh();

        $this->assertNotSame($t1, $t2);
    }

    // ------------------------------------------------------------------
    // validate
    // ------------------------------------------------------------------

    public function testValidateReturnsTrueForMatchingToken(): void
    {
        $token = str_repeat('b', 64);

        $this->session->method('get')->willReturn($token);

        $this->assertTrue($this->manager->validate($token));
    }

    public function testValidateReturnsFalseForWrongToken(): void
    {
        $this->session->method('get')->willReturn(str_repeat('a', 64));

        $this->assertFalse($this->manager->validate(str_repeat('b', 64)));
    }

    public function testValidateReturnsFalseForNullToken(): void
    {
        $this->session->method('get')->willReturn(str_repeat('a', 64));

        $this->assertFalse($this->manager->validate(null));
    }

    public function testValidateReturnsFalseForEmptyToken(): void
    {
        $this->session->method('get')->willReturn(str_repeat('a', 64));

        $this->assertFalse($this->manager->validate(''));
    }

    public function testValidateReturnsFalseWhenNoSessionToken(): void
    {
        $this->session->method('get')->willReturn('');

        $this->assertFalse($this->manager->validate('anytoken'));
    }
}
