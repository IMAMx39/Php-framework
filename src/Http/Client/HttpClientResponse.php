<?php

declare(strict_types=1);

namespace Framework\Http\Client;

class HttpClientResponse
{
    public function __construct(
        private readonly int    $statusCode,
        private readonly string $body,
        private readonly array  $headers = [],
    ) {}

    // ------------------------------------------------------------------
    // Corps
    // ------------------------------------------------------------------

    /** Décode le corps JSON en tableau associatif. */
    public function json(): array
    {
        return json_decode($this->body, associative: true) ?? [];
    }

    /** Retourne le corps brut. */
    public function text(): string
    {
        return $this->body;
    }

    // ------------------------------------------------------------------
    // Statut
    // ------------------------------------------------------------------

    public function status(): int
    {
        return $this->statusCode;
    }

    public function ok(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function redirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    public function clientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function serverError(): bool
    {
        return $this->statusCode >= 500;
    }

    public function failed(): bool
    {
        return $this->clientError() || $this->serverError();
    }

    // ------------------------------------------------------------------
    // Headers
    // ------------------------------------------------------------------

    /** Retourne la valeur du header (insensible à la casse). */
    public function header(string $name): ?string
    {
        $lower = strtolower($name);

        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $lower) {
                return $value;
            }
        }

        return null;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    // ------------------------------------------------------------------
    // Throw on failure
    // ------------------------------------------------------------------

    /**
     * Lève une HttpClientException si la réponse est 4xx ou 5xx.
     */
    public function throw(): static
    {
        if ($this->failed()) {
            throw new HttpClientException($this);
        }

        return $this;
    }
}
