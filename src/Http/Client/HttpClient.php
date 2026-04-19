<?php

declare(strict_types=1);

namespace Framework\Http\Client;

/**
 * Client HTTP fluent basé sur cURL.
 *
 * Usage :
 *   $client = new HttpClient(baseUrl: 'https://api.example.com');
 *
 *   $users = $client->get('/users')->json();
 *
 *   $resp = $client
 *       ->withToken($token)
 *       ->post('/articles', ['title' => 'Hello']);
 *
 *   $resp->throw()->json();   // lève HttpClientException si 4xx/5xx
 */
class HttpClient
{
    private string $baseUrl;
    private array  $headers;
    private int    $timeout;

    /** @var callable|null Surcharge du transport — utile pour les tests. */
    private $handler;

    public function __construct(
        string   $baseUrl = '',
        array    $headers = [],
        int      $timeout = 30,
        ?callable $handler = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->headers = $headers;
        $this->timeout = $timeout;
        $this->handler = $handler;
    }

    // ------------------------------------------------------------------
    // Builder — chaque méthode retourne un clone (immutabilité)
    // ------------------------------------------------------------------

    public function withHeaders(array $headers): static
    {
        $clone          = clone $this;
        $clone->headers = array_merge($this->headers, $headers);

        return $clone;
    }

    public function withToken(string $token, string $type = 'Bearer'): static
    {
        return $this->withHeaders(['Authorization' => "{$type} {$token}"]);
    }

    public function withBasicAuth(string $user, string $password): static
    {
        return $this->withHeaders([
            'Authorization' => 'Basic ' . base64_encode("{$user}:{$password}"),
        ]);
    }

    public function withTimeout(int $seconds): static
    {
        $clone          = clone $this;
        $clone->timeout = $seconds;

        return $clone;
    }

    // ------------------------------------------------------------------
    // Verbes HTTP
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $query Paramètres ajoutés à l'URL. */
    public function get(string $url, array $query = []): HttpClientResponse
    {
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $this->send('GET', $url);
    }

    /** @param array<string, mixed> $data Encodé en JSON. */
    public function post(string $url, array $data = []): HttpClientResponse
    {
        return $this->send('POST', $url, $data);
    }

    public function put(string $url, array $data = []): HttpClientResponse
    {
        return $this->send('PUT', $url, $data);
    }

    public function patch(string $url, array $data = []): HttpClientResponse
    {
        return $this->send('PATCH', $url, $data);
    }

    public function delete(string $url, array $data = []): HttpClientResponse
    {
        return $this->send('DELETE', $url, $data);
    }

    // ------------------------------------------------------------------
    // Transport
    // ------------------------------------------------------------------

    private function send(string $method, string $url, array $body = []): HttpClientResponse
    {
        $fullUrl = $this->resolveUrl($url);

        if ($this->handler !== null) {
            return ($this->handler)($method, $fullUrl, $body, $this->headers);
        }

        return $this->curlSend($method, $fullUrl, $body);
    }

    private function curlSend(string $method, string $url, array $body): HttpClientResponse
    {
        $ch = curl_init();

        $requestHeaders = array_merge(
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            $this->headers,
        );

        $curlHeaders = [];
        foreach ($requestHeaders as $name => $value) {
            $curlHeaders[] = "{$name}: {$value}";
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_HEADER         => true,
        ]);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

            if (!empty($body)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        }

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hSize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error  = curl_error($ch);

        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException("cURL error: {$error}");
        }

        $rawHeaders  = substr($raw, 0, $hSize);
        $responseBody = substr($raw, $hSize);
        $headers     = $this->parseHeaders($rawHeaders);

        return new HttpClientResponse($status, $responseBody, $headers);
    }

    private function resolveUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return $this->baseUrl . '/' . ltrim($url, '/');
    }

    private function parseHeaders(string $raw): array
    {
        $headers = [];

        foreach (explode("\r\n", $raw) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[trim($name)] = trim($value);
        }

        return $headers;
    }
}
