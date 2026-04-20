<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Framework\Auth\Gate;
use Framework\Exception\ForbiddenException;
use PHPUnit\Framework\TestCase;

// ── Fixtures ──────────────────────────────────────────────────────────

class FakeUser
{
    public function __construct(
        public readonly int    $id,
        public readonly string $role = 'user',
    ) {}
}

class FakePost
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
    ) {}
}

class FakePostPolicy
{
    public function edit(FakeUser $user, FakePost $post): bool
    {
        return $user->id === $post->userId;
    }

    public function delete(FakeUser $user, FakePost $post): bool
    {
        return $user->role === 'admin';
    }
}

// ── Tests ─────────────────────────────────────────────────────────────

class GateTest extends TestCase
{
    private function gate(?FakeUser $user): Gate
    {
        return new Gate(fn () => $user);
    }

    // ------------------------------------------------------------------
    // allows() / denies()
    // ------------------------------------------------------------------

    public function testAllowsReturnsFalseForGuestUser(): void
    {
        $gate = $this->gate(null);
        $gate->define('admin', fn ($u) => $u->role === 'admin');

        $this->assertFalse($gate->allows('admin'));
        $this->assertTrue($gate->denies('admin'));
    }

    public function testAllowsDefinedAbility(): void
    {
        $user = new FakeUser(1, 'admin');
        $gate = $this->gate($user);
        $gate->define('admin', fn ($u) => $u->role === 'admin');

        $this->assertTrue($gate->allows('admin'));
        $this->assertFalse($gate->denies('admin'));
    }

    public function testDeniesDefinedAbility(): void
    {
        $user = new FakeUser(1, 'user');
        $gate = $this->gate($user);
        $gate->define('admin', fn ($u) => $u->role === 'admin');

        $this->assertFalse($gate->allows('admin'));
    }

    public function testUndefinedAbilityReturnsFalse(): void
    {
        $user = new FakeUser(1);
        $gate = $this->gate($user);

        $this->assertFalse($gate->allows('nonexistent'));
    }

    // ------------------------------------------------------------------
    // authorize()
    // ------------------------------------------------------------------

    public function testAuthorizePassesForAllowedAbility(): void
    {
        $user = new FakeUser(1, 'admin');
        $gate = $this->gate($user);
        $gate->define('admin', fn ($u) => $u->role === 'admin');

        $this->expectNotToPerformAssertions();
        $gate->authorize('admin');
    }

    public function testAuthorizeThrowsForbiddenException(): void
    {
        $user = new FakeUser(1, 'user');
        $gate = $this->gate($user);
        $gate->define('admin', fn ($u) => $u->role === 'admin');

        $this->expectException(ForbiddenException::class);
        $gate->authorize('admin');
    }

    // ------------------------------------------------------------------
    // hasRole()
    // ------------------------------------------------------------------

    public function testHasRole(): void
    {
        $admin = new FakeUser(1, 'admin');
        $gate  = $this->gate($admin);

        $this->assertTrue($gate->hasRole('admin'));
        $this->assertTrue($gate->hasRole('admin', 'moderator'));
        $this->assertFalse($gate->hasRole('moderator'));
    }

    public function testHasRoleReturnsFalseForGuest(): void
    {
        $gate = $this->gate(null);
        $this->assertFalse($gate->hasRole('admin'));
    }

    // ------------------------------------------------------------------
    // Policies
    // ------------------------------------------------------------------

    public function testPolicyAllows(): void
    {
        $user = new FakeUser(1);
        $post = new FakePost(10, 1); // owner
        $gate = $this->gate($user);
        $gate->policy(FakePost::class, FakePostPolicy::class);

        $this->assertTrue($gate->allows('edit', $post));
    }

    public function testPolicyDenies(): void
    {
        $user = new FakeUser(2);
        $post = new FakePost(10, 1); // NOT owner
        $gate = $this->gate($user);
        $gate->policy(FakePost::class, FakePostPolicy::class);

        $this->assertFalse($gate->allows('edit', $post));
    }

    public function testPolicyFallsBackToDefinition(): void
    {
        $user = new FakeUser(1, 'admin');
        $post = new FakePost(10, 99); // not owner, but admin via gate definition
        $gate = $this->gate($user);
        $gate->policy(FakePost::class, FakePostPolicy::class);
        // Policy has 'delete' method — it checks role, not ownership
        $this->assertTrue($gate->allows('delete', $post));
    }

    public function testPolicyMissingMethodFallsBackToDefinition(): void
    {
        $user = new FakeUser(1, 'admin');
        $post = new FakePost(10, 99);
        $gate = $this->gate($user);
        $gate->policy(FakePost::class, FakePostPolicy::class);
        // 'publish' is not on the policy — falls back to gate definition
        $gate->define('publish', fn ($u) => $u->role === 'admin');

        $this->assertTrue($gate->allows('publish', $post));
    }

    public function testNoPolicyForModelUsesDefinition(): void
    {
        $user = new FakeUser(1, 'admin');
        $post = new FakePost(10, 1);
        $gate = $this->gate($user);
        $gate->define('edit', fn ($u, $p) => $u->id === $p->userId);
        // No policy registered for FakePost

        $this->assertTrue($gate->allows('edit', $post));
    }
}
