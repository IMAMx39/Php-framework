<?php

declare(strict_types=1);

namespace Tests\Unit\RateLimiter;

use Framework\Cache\ArrayCache;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Middleware\ThrottleMiddleware;
use Framework\RateLimiter\RateLimiter;
use PHPUnit\Framework\TestCase;

class ThrottleMiddlewareTest extends TestCase
{
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->limiter = new RateLimiter(new ArrayCache());
    }

    private function makeRequest(string $ip = '1.2.3.4'): Request
    {
        return new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/',
            'REMOTE_ADDR'    => $ip,
        ], [], null);
    }

    private function nextOk(): callable
    {
        return fn (Request $r) => new Response('ok', 200);
    }

    // ------------------------------------------------------------------

    public function testRequestUnderLimitIsPassedThrough(): void
    {
        $middleware = new ThrottleMiddleware($this->limiter, maxAttempts: 5, decaySeconds: 60);
        $response   = $middleware->process($this->makeRequest(), $this->nextOk());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRequestOverLimitReturns429(): void
    {
        $middleware = new ThrottleMiddleware($this->limiter, maxAttempts: 3, decaySeconds: 60);
        $request    = $this->makeRequest();

        // Épuise la limite
        $middleware->process($request, $this->nextOk());
        $middleware->process($request, $this->nextOk());
        $middleware->process($request, $this->nextOk());

        $response = $middleware->process($request, $this->nextOk());

        $this->assertSame(429, $response->getStatusCode());
    }

    public function testRateLimitHeadersAreAdded(): void
    {
        $middleware = new ThrottleMiddleware($this->limiter, maxAttempts: 10, decaySeconds: 60);
        $response   = $middleware->process($this->makeRequest(), $this->nextOk());

        $headers = $response->getHeaders();

        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertSame('10', $headers['X-RateLimit-Limit']);
        $this->assertSame('9', $headers['X-RateLimit-Remaining']);
    }

    public function testRetryAfterHeaderOn429(): void
    {
        $middleware = new ThrottleMiddleware($this->limiter, maxAttempts: 1, decaySeconds: 30);
        $request    = $this->makeRequest();

        $middleware->process($request, $this->nextOk());
        $response = $middleware->process($request, $this->nextOk());

        $this->assertSame('30', $response->getHeaders()['Retry-After']);
    }

    public function testDifferentIpsAreTrackedSeparately(): void
    {
        $middleware = new ThrottleMiddleware($this->limiter, maxAttempts: 2, decaySeconds: 60);

        // IP A épuise sa limite
        $middleware->process($this->makeRequest('10.0.0.1'), $this->nextOk());
        $middleware->process($this->makeRequest('10.0.0.1'), $this->nextOk());
        $responseA = $middleware->process($this->makeRequest('10.0.0.1'), $this->nextOk());

        // IP B est encore sous la limite
        $responseB = $middleware->process($this->makeRequest('10.0.0.2'), $this->nextOk());

        $this->assertSame(429, $responseA->getStatusCode());
        $this->assertSame(200, $responseB->getStatusCode());
    }
}
