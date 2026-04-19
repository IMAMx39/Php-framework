<?php

declare(strict_types=1);

namespace Tests\Unit\Debug;

use Framework\Debug\Collector\MemoryCollector;
use Framework\Debug\Collector\QueryCollector;
use Framework\Debug\QueryLog;
use Framework\Debug\Toolbar\ToolbarMiddleware;
use Framework\Http\Request;
use Framework\Http\Response;
use PHPUnit\Framework\TestCase;

class ToolbarMiddlewareTest extends TestCase
{
    private function makeMiddleware(): ToolbarMiddleware
    {
        return new ToolbarMiddleware([
            new MemoryCollector(),
            new QueryCollector(new QueryLog()),
        ]);
    }

    private function makeRequest(): Request
    {
        return Request::fromGlobals();
    }

    public function testInjectsToolbarInHtmlResponse(): void
    {
        $mw = $this->makeMiddleware();

        $response = $mw->process(
            $this->makeRequest(),
            fn () => new Response('<html><body><p>Hello</p></body></html>', 200, ['Content-Type' => 'text/html']),
        );

        $this->assertStringContainsString('__debugbar', $response->getContent());
    }

    public function testDoesNotInjectInJsonResponse(): void
    {
        $mw = $this->makeMiddleware();

        $response = $mw->process(
            $this->makeRequest(),
            fn () => new Response('{"ok":true}', 200, ['Content-Type' => 'application/json']),
        );

        $this->assertStringNotContainsString('__debugbar', $response->getContent());
    }

    public function testDoesNotInjectWhenNoBodyTag(): void
    {
        $mw = $this->makeMiddleware();

        $response = $mw->process(
            $this->makeRequest(),
            fn () => new Response('<p>fragment</p>', 200, ['Content-Type' => 'text/html']),
        );

        $this->assertStringNotContainsString('__debugbar', $response->getContent());
    }

    public function testPreservesStatusCode(): void
    {
        $mw = $this->makeMiddleware();

        $response = $mw->process(
            $this->makeRequest(),
            fn () => new Response('<html><body></body></html>', 404, ['Content-Type' => 'text/html']),
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testToolbarContainsStatusCode(): void
    {
        $mw = $this->makeMiddleware();

        $response = $mw->process(
            $this->makeRequest(),
            fn () => new Response('<html><body></body></html>', 200, ['Content-Type' => 'text/html']),
        );

        $this->assertStringContainsString('200', $response->getContent());
    }

    public function testToolbarIsInjectedBeforeClosingBodyTag(): void
    {
        $mw = $this->makeMiddleware();

        $response = $mw->process(
            $this->makeRequest(),
            fn () => new Response('<html><body><p>content</p></body></html>', 200, ['Content-Type' => 'text/html']),
        );

        $content = $response->getContent();
        $this->assertGreaterThan(
            strpos($content, '__debugbar'),
            strpos($content, '</body>'),
        );
    }
}
