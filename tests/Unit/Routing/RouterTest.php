<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use Framework\Exception\HttpNotFoundException;
use Framework\Exception\MethodNotAllowedException;
use Framework\Http\Request;
use Framework\Routing\Route;
use Framework\Routing\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    // ------------------------------------------------------------------
    // Enregistrement
    // ------------------------------------------------------------------

    public function testGetRegistersRoute(): void
    {
        $route = $this->router->get('/users', fn () => 'handler');

        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame('/users', $route->getPath());
        $this->assertSame(['GET'], $route->getMethods());
    }

    public function testPostRegistersRoute(): void
    {
        $route = $this->router->post('/users', fn () => null);

        $this->assertSame(['POST'], $route->getMethods());
    }

    public function testPutPatchDeleteRegisterRoutes(): void
    {
        $put    = $this->router->put('/a', fn () => null);
        $patch  = $this->router->patch('/b', fn () => null);
        $delete = $this->router->delete('/c', fn () => null);

        $this->assertSame(['PUT'],    $put->getMethods());
        $this->assertSame(['PATCH'],  $patch->getMethods());
        $this->assertSame(['DELETE'], $delete->getMethods());
    }

    public function testNamedRouteIsRegistered(): void
    {
        $this->router->get('/home', fn () => null, 'home');

        $url = $this->router->generate('home');

        $this->assertSame('/home', $url);
    }

    // ------------------------------------------------------------------
    // Correspondance — chemins statiques
    // ------------------------------------------------------------------

    public function testMatchStaticRoute(): void
    {
        $this->router->get('/hello', fn () => 'hello', 'hello');

        $request = $this->makeRequest('GET', '/hello');
        $route   = $this->router->match($request);

        $this->assertSame('hello', $route->getName());
    }

    public function testMatchReturnsCorrectHandler(): void
    {
        $handler = fn () => 'response';
        $this->router->get('/ping', $handler);

        $route = $this->router->match($this->makeRequest('GET', '/ping'));

        $this->assertSame($handler, $route->getHandler());
    }

    // ------------------------------------------------------------------
    // Correspondance — paramètres dynamiques
    // ------------------------------------------------------------------

    public function testMatchDynamicParam(): void
    {
        $this->router->get('/users/{id}', fn () => null, 'user.show');

        $route = $this->router->match($this->makeRequest('GET', '/users/42'));

        $this->assertSame(['id' => '42'], $route->getParameters());
    }

    public function testMatchMultipleParams(): void
    {
        $this->router->get('/posts/{post}/comments/{comment}', fn () => null);

        $route = $this->router->match($this->makeRequest('GET', '/posts/10/comments/99'));

        $this->assertSame(['post' => '10', 'comment' => '99'], $route->getParameters());
    }

    // ------------------------------------------------------------------
    // Exceptions
    // ------------------------------------------------------------------

    public function testMatchThrowsNotFoundForUnknownPath(): void
    {
        $this->expectException(HttpNotFoundException::class);

        $this->router->match($this->makeRequest('GET', '/not-found'));
    }

    public function testMatchThrowsMethodNotAllowed(): void
    {
        $this->router->get('/resource', fn () => null);

        $this->expectException(MethodNotAllowedException::class);

        $this->router->match($this->makeRequest('POST', '/resource'));
    }

    // ------------------------------------------------------------------
    // Génération d'URL
    // ------------------------------------------------------------------

    public function testGenerateUrlWithParams(): void
    {
        $this->router->get('/users/{id}/posts/{slug}', fn () => null, 'user.posts');

        $url = $this->router->generate('user.posts', ['id' => '5', 'slug' => 'hello-world']);

        $this->assertSame('/users/5/posts/hello-world', $url);
    }

    public function testGenerateThrowsForUnknownName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->router->generate('unknown.route');
    }

    // ------------------------------------------------------------------
    // Méthodes HTTP normalisées
    // ------------------------------------------------------------------

    public function testMethodIsCaseInsensitiveOnRegistration(): void
    {
        $this->router->add('/x', ['get', 'Post'], fn () => null, 'x');

        $route = $this->router->match($this->makeRequest('POST', '/x'));

        $this->assertSame('x', $route->getName());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeRequest(string $method, string $uri): Request
    {
        return new Request(
            query:   [],
            body:    [],
            server:  ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            cookies: [],
            content: null,
        );
    }
}
