<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Middleware\CsrfMiddleware;
use Framework\Security\CsrfTokenManager;
use PHPUnit\Framework\TestCase;

class CsrfMiddlewareTest extends TestCase
{
    private CsrfTokenManager $manager;
    private CsrfMiddleware   $middleware;

    protected function setUp(): void
    {
        $this->manager    = $this->createMock(CsrfTokenManager::class);
        $this->middleware = new CsrfMiddleware($this->manager);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeRequest(
        string $method,
        string $uri = '/',
        array  $body = [],
        array  $headers = [],
    ): Request {
        $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri];

        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return new Request([], $body, $server, [], null);
    }

    private function nextOk(): callable
    {
        return fn(Request $r) => new Response('ok', 200);
    }

    // ------------------------------------------------------------------
    // GET / HEAD sont transparents
    // ------------------------------------------------------------------

    public function testGetRequestIsPassedThrough(): void
    {
        $request = $this->makeRequest('GET');

        $this->manager->expects($this->never())->method('validate');

        $response = $this->middleware->process($request, $this->nextOk());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHeadRequestIsPassedThrough(): void
    {
        $request = $this->makeRequest('HEAD');

        $this->manager->expects($this->never())->method('validate');

        $response = $this->middleware->process($request, $this->nextOk());

        $this->assertSame(200, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    // POST / PUT / PATCH / DELETE requièrent un token valide
    // ------------------------------------------------------------------

    /** @dataProvider unsafeMethodProvider */
    public function testValidTokenIsAccepted(string $method): void
    {
        $this->manager->method('validate')->willReturn(true);

        $request  = $this->makeRequest($method, '/', ['_csrf_token' => 'valid-token']);
        $response = $this->middleware->process($request, $this->nextOk());

        $this->assertSame(200, $response->getStatusCode());
    }

    /** @dataProvider unsafeMethodProvider */
    public function testInvalidTokenReturns403(string $method): void
    {
        $this->manager->method('validate')->willReturn(false);

        $request  = $this->makeRequest($method, '/', ['_csrf_token' => 'bad']);
        $response = $this->middleware->process($request, $this->nextOk());

        $this->assertSame(403, $response->getStatusCode());
    }

    /** @dataProvider unsafeMethodProvider */
    public function testMissingTokenReturns403(string $method): void
    {
        $this->manager->method('validate')->willReturn(false);

        $request  = $this->makeRequest($method);
        $response = $this->middleware->process($request, $this->nextOk());

        $this->assertSame(403, $response->getStatusCode());
    }

    public static function unsafeMethodProvider(): array
    {
        return [['POST'], ['PUT'], ['PATCH'], ['DELETE']];
    }

    // ------------------------------------------------------------------
    // Token dans l'en-tête (AJAX)
    // ------------------------------------------------------------------

    public function testTokenFromHeaderIsAccepted(): void
    {
        $this->manager->method('validate')->willReturn(true);

        $request  = $this->makeRequest('POST', '/', [], ['X-CSRF-TOKEN' => 'valid']);
        $response = $this->middleware->process($request, $this->nextOk());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPostBodyTokenTakesPrecedenceOverHeader(): void
    {
        $this->manager
            ->expects($this->once())
            ->method('validate')
            ->with('body-token')
            ->willReturn(true);

        $request = $this->makeRequest(
            'POST',
            '/',
            ['_csrf_token' => 'body-token'],
            ['X-CSRF-TOKEN' => 'header-token'],
        );

        $this->middleware->process($request, $this->nextOk());
    }

    // ------------------------------------------------------------------
    // Exemptions par préfixe d'URI
    // ------------------------------------------------------------------

    public function testExemptedPathSkipsValidation(): void
    {
        $middleware = new CsrfMiddleware($this->manager, exemptPaths: ['/api/']);

        $this->manager->expects($this->never())->method('validate');

        $request  = $this->makeRequest('POST', '/api/webhooks');
        $response = $middleware->process($request, $this->nextOk());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testNonExemptedPathIsStillProtected(): void
    {
        $middleware = new CsrfMiddleware($this->manager, exemptPaths: ['/api/']);

        $this->manager->method('validate')->willReturn(false);

        $request  = $this->makeRequest('POST', '/contact');
        $response = $middleware->process($request, $this->nextOk());

        $this->assertSame(403, $response->getStatusCode());
    }
}
