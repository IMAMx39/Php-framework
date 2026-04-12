<?php

declare(strict_types=1);

namespace Framework\Http;

class Request
{
    private array $headers;

    public function __construct(
        private readonly array $query,
        private readonly array $body,
        private readonly array $server,
        private readonly array $cookies,
        private readonly ?string $content,
    ) {
        $this->headers = $this->parseHeaders($server);
    }

    public static function fromGlobals(): static
    {
        $content = file_get_contents('php://input') ?: null;

        return new static($_GET, $_POST, $_SERVER, $_COOKIE, $content);
    }

    public function getMethod(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function getUri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $pos = strpos($uri, '?');

        return $pos !== false ? substr($uri, 0, $pos) : $uri;
    }

    public function getQueryString(): ?string
    {
        return $this->server['QUERY_STRING'] ?: null;
    }

    /**
     * Récupère un paramètre GET.
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Récupère un paramètre POST.
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * Récupère un paramètre GET ou POST (GET en priorité).
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $this->body[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->body, $this->query);
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Décode le body JSON et le retourne sous forme de tableau.
     */
    public function json(): mixed
    {
        if ($this->content === null) {
            return null;
        }

        return json_decode($this->content, true);
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = strtoupper(str_replace('-', '_', $name));

        return $this->headers[$key] ?? $default;
    }

    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    public function isJson(): bool
    {
        return str_contains($this->header('CONTENT_TYPE', ''), 'application/json');
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    private function parseHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[substr($key, 5)] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[$key] = $value;
            }
        }

        return $headers;
    }
}
