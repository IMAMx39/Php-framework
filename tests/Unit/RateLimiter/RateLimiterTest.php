<?php

declare(strict_types=1);

namespace Tests\Unit\RateLimiter;

use Framework\Cache\ArrayCache;
use Framework\RateLimiter\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->limiter = new RateLimiter(new ArrayCache());
    }

    // ------------------------------------------------------------------
    // hit / attempts
    // ------------------------------------------------------------------

    public function testHitIncrementsCounter(): void
    {
        $this->limiter->hit('test', 60);
        $this->limiter->hit('test', 60);

        $this->assertSame(2, $this->limiter->attempts('test'));
    }

    public function testAttemptsIsZeroBeforeFirstHit(): void
    {
        $this->assertSame(0, $this->limiter->attempts('new-key'));
    }

    // ------------------------------------------------------------------
    // tooManyAttempts
    // ------------------------------------------------------------------

    public function testNotTooManyAttemptsInitially(): void
    {
        $this->assertFalse($this->limiter->tooManyAttempts('key', 5));
    }

    public function testTooManyAttemptsAfterLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit('key', 60);
        }

        $this->assertTrue($this->limiter->tooManyAttempts('key', 5));
    }

    public function testNotTooManyAttemptsJustBeforeLimit(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->limiter->hit('key', 60);
        }

        $this->assertFalse($this->limiter->tooManyAttempts('key', 5));
    }

    // ------------------------------------------------------------------
    // attempt (hit + check)
    // ------------------------------------------------------------------

    public function testAttemptReturnsTrueUnderLimit(): void
    {
        $this->assertTrue($this->limiter->attempt('k', 3, 60));
        $this->assertTrue($this->limiter->attempt('k', 3, 60));
    }

    public function testAttemptReturnsFalseWhenLimitReached(): void
    {
        $this->limiter->attempt('k', 3, 60);
        $this->limiter->attempt('k', 3, 60);
        $this->limiter->attempt('k', 3, 60);

        $this->assertFalse($this->limiter->attempt('k', 3, 60));
    }

    // ------------------------------------------------------------------
    // remaining
    // ------------------------------------------------------------------

    public function testRemainingDecreasesWithEachHit(): void
    {
        $this->assertSame(5, $this->limiter->remaining('r', 5));

        $this->limiter->hit('r', 60);
        $this->assertSame(4, $this->limiter->remaining('r', 5));

        $this->limiter->hit('r', 60);
        $this->assertSame(3, $this->limiter->remaining('r', 5));
    }

    public function testRemainingNeverGoesBelowZero(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->limiter->hit('over', 60);
        }

        $this->assertSame(0, $this->limiter->remaining('over', 5));
    }

    // ------------------------------------------------------------------
    // clear
    // ------------------------------------------------------------------

    public function testClearResetsCounter(): void
    {
        $this->limiter->hit('c', 60);
        $this->limiter->hit('c', 60);
        $this->limiter->clear('c');

        $this->assertSame(0, $this->limiter->attempts('c'));
        $this->assertFalse($this->limiter->tooManyAttempts('c', 3));
    }

    // ------------------------------------------------------------------
    // Isolation des clés
    // ------------------------------------------------------------------

    public function testDifferentKeysAreIsolated(): void
    {
        $this->limiter->hit('a', 60);
        $this->limiter->hit('a', 60);
        $this->limiter->hit('b', 60);

        $this->assertSame(2, $this->limiter->attempts('a'));
        $this->assertSame(1, $this->limiter->attempts('b'));
    }
}
