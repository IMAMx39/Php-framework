<?php

declare(strict_types=1);

namespace Framework\Debug\Toolbar;

use Framework\Debug\Collector\CollectorInterface;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Middleware\MiddlewareInterface;

class ToolbarMiddleware implements MiddlewareInterface
{
    /** @param CollectorInterface[] $collectors */
    public function __construct(private readonly array $collectors) {}

    public function process(Request $request, callable $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$this->shouldInject($response)) {
            return $response;
        }

        $renderer = new ToolbarRenderer($this->collectors);
        $toolbar  = $renderer->render($response->getStatusCode());
        $body     = str_ireplace('</body>', $toolbar . '</body>', $response->getContent());

        return new Response($body, $response->getStatusCode(), $response->getHeaders());
    }

    private function shouldInject(Response $response): bool
    {
        $headers     = $response->getHeaders();
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';

        return str_contains($contentType, 'text/html')
            && str_contains($response->getContent(), '</body>');
    }
}
