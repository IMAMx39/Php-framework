<?php

declare(strict_types=1);

namespace Framework\Http;

class Response
{
    public function __construct(
        protected string $content = '',
        protected int $statusCode = 200,
        protected array $headers = [],
    ) {}

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function setHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        echo $this->content;
    }

    public static function make(string $content = '', int $status = 200, array $headers = []): static
    {
        return new static($content, $status, $headers);
    }

    public static function redirect(string $url, int $status = 302): static
    {
        return new static('', $status, ['Location' => $url]);
    }
}
