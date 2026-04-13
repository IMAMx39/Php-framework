<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Entity\User;
use App\Repository\UserRepository;
use Framework\Auth\Auth;
use Framework\Session\Session;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    /** @var Session&MockObject */
    private Session $session;

    /** @var UserRepository&MockObject */
    private UserRepository $users;

    private Auth $auth;

    protected function setUp(): void
    {
        $this->session = $this->createMock(Session::class);
        $this->users   = $this->createMock(UserRepository::class);
        $this->auth    = new Auth($this->session, $this->users);
    }

    // ------------------------------------------------------------------
    // attempt()
    // ------------------------------------------------------------------

    public function testAttemptSucceedsWithCorrectCredentials(): void
    {
        $user = $this->makeUser('alice@example.com', 'secret');

        $this->users->method('findByEmail')->with('alice@example.com')->willReturn($user);
        $this->session->expects($this->once())->method('regenerate');
        $this->session->expects($this->once())->method('set');

        $result = $this->auth->attempt('alice@example.com', 'secret');

        $this->assertTrue($result);
    }

    public function testAttemptFailsWithWrongPassword(): void
    {
        $user = $this->makeUser('alice@example.com', 'correct');

        $this->users->method('findByEmail')->willReturn($user);

        $result = $this->auth->attempt('alice@example.com', 'wrong');

        $this->assertFalse($result);
    }

    public function testAttemptFailsWhenUserNotFound(): void
    {
        $this->users->method('findByEmail')->willReturn(null);

        $result = $this->auth->attempt('ghost@example.com', 'password');

        $this->assertFalse($result);
    }

    public function testAttemptFailsForInactiveUser(): void
    {
        $user = $this->makeUser('alice@example.com', 'secret', active: false);

        $this->users->method('findByEmail')->willReturn($user);

        $result = $this->auth->attempt('alice@example.com', 'secret');

        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    // login() / logout()
    // ------------------------------------------------------------------

    public function testLoginStoresUserIdInSession(): void
    {
        $user = $this->makeUser('alice@example.com', 'secret');
        $user->setIdForTest(7);

        $this->session->expects($this->once())->method('regenerate');
        $this->session->expects($this->once())->method('set')->with('_auth_user_id', 7);

        $this->auth->login($user);
    }

    public function testLogoutClearsSession(): void
    {
        $this->session->expects($this->once())->method('remove')->with('_auth_user_id');
        $this->session->expects($this->once())->method('regenerate');

        $this->auth->logout();
    }

    public function testAfterLogoutUserIsNull(): void
    {
        $user = $this->makeUser('alice@example.com', 'secret');
        $user->setIdForTest(1);

        // Après logout(), resolvedUser est vidé et session->get() renvoie null
        $this->session->method('get')->willReturn(null);

        $this->auth->login($user);
        $this->auth->logout();

        $this->assertNull($this->auth->user());
    }

    // ------------------------------------------------------------------
    // check() / guest()
    // ------------------------------------------------------------------

    public function testCheckReturnsTrueWhenSessionHasUserId(): void
    {
        $this->session->method('has')->with('_auth_user_id')->willReturn(true);

        $this->assertTrue($this->auth->check());
        $this->assertFalse($this->auth->guest());
    }

    public function testGuestReturnsTrueWhenNotLoggedIn(): void
    {
        $this->session->method('has')->with('_auth_user_id')->willReturn(false);

        $this->assertTrue($this->auth->guest());
        $this->assertFalse($this->auth->check());
    }

    // ------------------------------------------------------------------
    // user() / id()
    // ------------------------------------------------------------------

    public function testUserReturnsNullWhenNotLoggedIn(): void
    {
        $this->session->method('has')->willReturn(false);
        $this->session->method('get')->willReturn(null);

        $this->assertNull($this->auth->user());
        $this->assertNull($this->auth->id());
    }

    public function testUserReturnsEntityWhenLoggedIn(): void
    {
        $user = $this->makeUser('alice@example.com', 'secret');
        $user->setIdForTest(5);

        $this->session->method('has')->willReturn(true);
        $this->session->method('get')->willReturn(5);
        $this->users->method('find')->with(5)->willReturn($user);

        $this->assertSame($user, $this->auth->user());
        $this->assertSame(5, $this->auth->id());
    }

    public function testUserIsCachedAfterFirstCall(): void
    {
        $user = $this->makeUser('alice@example.com', 'secret');
        $user->setIdForTest(3);

        $this->session->method('has')->willReturn(true);
        $this->session->method('get')->willReturn(3);

        // find() ne doit être appelé qu'une seule fois malgré deux appels à user()
        $this->users->expects($this->once())->method('find')->willReturn($user);

        $this->auth->user();
        $this->auth->user();
    }

    public function testLoginCachesUserWithoutExtraQuery(): void
    {
        $user = $this->makeUser('alice@example.com', 'secret');
        $user->setIdForTest(8);

        $this->session->method('regenerate');
        $this->session->method('set');

        // Après login(), user() ne doit pas requêter la BDD
        $this->users->expects($this->never())->method('find');

        $this->auth->login($user);

        $this->assertSame($user, $this->auth->user());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeUser(string $email, string $plainPassword, bool $active = true): UserTestDouble
    {
        $user = new UserTestDouble($email);
        $user->setPassword($plainPassword);
        $user->setActive($active);

        return $user;
    }
}

/**
 * Double de test pour User : ajoute setIdForTest() pour injecter un ID
 * sans passer par la persistance.
 */
class UserTestDouble extends User
{
    public function __construct(string $email)
    {
        parent::__construct('Test User', $email);
    }

    public function setIdForTest(int $id): void
    {
        $prop = new \ReflectionProperty(User::class, 'id');
        $prop->setAccessible(true);
        $prop->setValue($this, $id);
    }
}
