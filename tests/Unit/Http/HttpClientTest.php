<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Framework\Http\Client\HttpClient;
use Framework\Http\Client\HttpClientResponse;
use PHPUnit\Framework\TestCase;

class HttpClientTest extends TestCase
{
    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Crée un client dont le transport enregistre la dernière requête. */
    private function mockClient(int $status = 200, array $body = [], ?array &$captured = null): HttpClient
    {
        return new HttpClient(
            handler: function (string $method, string $url, array $data, array $headers) use ($status, $body, &$captured): HttpClientResponse {
                $captured = compact('method', 'url', 'data', 'headers');
                return new HttpClientResponse($status, json_encode($body) ?: '{}');
            },
        );
    }

    // ------------------------------------------------------------------
    // URL building
    // ------------------------------------------------------------------

    public function testBaseUrlIsPrependedToPath(): void
    {
        $client = new HttpClient(
            baseUrl: 'https://api.example.com',
            handler: function (string $method, string $url) {
                $this->assertSame('https://api.example.com/users', $url);
                return new HttpClientResponse(200, '[]');
            },
        );

        $client->get('/users');
    }

    public function testAbsoluteUrlSkipsBaseUrl(): void
    {
        $client = new HttpClient(
            baseUrl: 'https://api.example.com',
            handler: function (string $method, string $url) {
                $this->assertSame('https://other.io/v2/data', $url);
                return new HttpClientResponse(200, '{}');
            },
        );

        $client->get('https://other.io/v2/data');
    }

    public function testQueryParamsAreAppendedToUrl(): void
    {
        $client = new HttpClient(
            handler: function (string $method, string $url) {
                $this->assertStringContainsString('page=2', $url);
                $this->assertStringContainsString('limit=10', $url);
                return new HttpClientResponse(200, '{}');
            },
        );

        $client->get('/items', ['page' => 2, 'limit' => 10]);
    }

    // ------------------------------------------------------------------
    // Verbes
    // ------------------------------------------------------------------

    public function testGetSendsGetMethod(): void
    {
        $client = $this->mockClient(captured: $cap);
        $client->get('/ping');
        $this->assertSame('GET', $cap['method']);
    }

    public function testPostSendsPostMethod(): void
    {
        $client = $this->mockClient(captured: $cap);
        $client->post('/articles', ['title' => 'Hi']);
        $this->assertSame('POST', $cap['method']);
        $this->assertSame(['title' => 'Hi'], $cap['data']);
    }

    public function testPutSendsPutMethod(): void
    {
        $client = $this->mockClient(captured: $cap);
        $client->put('/articles/1', ['title' => 'Updated']);
        $this->assertSame('PUT', $cap['method']);
    }

    public function testPatchSendsPatchMethod(): void
    {
        $client = $this->mockClient(captured: $cap);
        $client->patch('/articles/1', ['title' => 'Patched']);
        $this->assertSame('PATCH', $cap['method']);
    }

    public function testDeleteSendsDeleteMethod(): void
    {
        $client = $this->mockClient(captured: $cap);
        $client->delete('/articles/1');
        $this->assertSame('DELETE', $cap['method']);
    }

    // ------------------------------------------------------------------
    // Builder (immutabilité)
    // ------------------------------------------------------------------

    public function testWithHeadersMergesHeaders(): void
    {
        $client = $this->mockClient(captured: $cap);
        $client->withHeaders(['X-App' => 'test'])->get('/');
        $this->assertSame('test', $cap['headers']['X-App']);
    }

    public function testWithHeadersDoesNotMutateOriginal(): void
    {
        $original = $this->mockClient(captured: $cap);
        $clone    = $original->withHeaders(['X-App' => 'test']);

        $original->get('/');
        $this->assertArrayNotHasKey('X-App', $cap['headers']);

        $clone->get('/');
        $this->assertArrayHasKey('X-App', $cap['headers']);
    }

    public function testWithTokenAddsAuthorizationHeader(): void
    {
        $client = $this->mockClient(captured: $cap);
        $client->withToken('abc123')->get('/me');
        $this->assertSame('Bearer abc123', $cap['headers']['Authorization']);
    }

    public function testWithTokenAcceptsCustomType(): void
    {
        $client = $this->mockClient(captured: $cap);
        $client->withToken('xyz', 'Token')->get('/me');
        $this->assertSame('Token xyz', $cap['headers']['Authorization']);
    }

    public function testWithBasicAuthEncodesCredentials(): void
    {
        $client = $this->mockClient(captured: $cap);
        $client->withBasicAuth('user', 'pass')->get('/secure');
        $expected = 'Basic ' . base64_encode('user:pass');
        $this->assertSame($expected, $cap['headers']['Authorization']);
    }

    // ------------------------------------------------------------------
    // Réponse
    // ------------------------------------------------------------------

    public function testResponseIsHttpClientResponse(): void
    {
        $client   = $this->mockClient(200, ['id' => 1]);
        $response = $client->get('/item/1');

        $this->assertInstanceOf(\Framework\Http\Client\HttpClientResponse::class, $response);
        $this->assertSame(200, $response->status());
        $this->assertSame(['id' => 1], $response->json());
    }
}
