<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Framework\Http\Client\HttpClientException;
use Framework\Http\Client\HttpClientResponse;
use PHPUnit\Framework\TestCase;

class HttpClientResponseTest extends TestCase
{
    // ------------------------------------------------------------------
    // Corps
    // ------------------------------------------------------------------

    public function testTextReturnsRawBody(): void
    {
        $r = new HttpClientResponse(200, 'hello');
        $this->assertSame('hello', $r->text());
    }

    public function testJsonDecodesBody(): void
    {
        $r = new HttpClientResponse(200, '{"name":"Alice","age":30}');
        $this->assertSame(['name' => 'Alice', 'age' => 30], $r->json());
    }

    public function testJsonReturnsEmptyArrayOnInvalidJson(): void
    {
        $r = new HttpClientResponse(200, 'not-json');
        $this->assertSame([], $r->json());
    }

    // ------------------------------------------------------------------
    // Statut
    // ------------------------------------------------------------------

    public function testOkForTwoHundreds(): void
    {
        foreach ([200, 201, 204, 299] as $code) {
            $this->assertTrue((new HttpClientResponse($code, ''))->ok(), "Expected ok() for $code");
        }
    }

    public function testNotOkOutsideTwoHundreds(): void
    {
        foreach ([301, 400, 404, 500] as $code) {
            $this->assertFalse((new HttpClientResponse($code, ''))->ok(), "Expected !ok() for $code");
        }
    }

    public function testClientError(): void
    {
        $this->assertTrue((new HttpClientResponse(400, ''))->clientError());
        $this->assertTrue((new HttpClientResponse(404, ''))->clientError());
        $this->assertFalse((new HttpClientResponse(500, ''))->clientError());
    }

    public function testServerError(): void
    {
        $this->assertTrue((new HttpClientResponse(500, ''))->serverError());
        $this->assertTrue((new HttpClientResponse(503, ''))->serverError());
        $this->assertFalse((new HttpClientResponse(404, ''))->serverError());
    }

    public function testFailed(): void
    {
        $this->assertTrue((new HttpClientResponse(422, ''))->failed());
        $this->assertTrue((new HttpClientResponse(500, ''))->failed());
        $this->assertFalse((new HttpClientResponse(200, ''))->failed());
    }

    public function testRedirect(): void
    {
        $this->assertTrue((new HttpClientResponse(301, ''))->redirect());
        $this->assertFalse((new HttpClientResponse(200, ''))->redirect());
    }

    public function testStatus(): void
    {
        $this->assertSame(201, (new HttpClientResponse(201, ''))->status());
    }

    // ------------------------------------------------------------------
    // Headers
    // ------------------------------------------------------------------

    public function testHeaderReturnsValue(): void
    {
        $r = new HttpClientResponse(200, '', ['Content-Type' => 'application/json']);
        $this->assertSame('application/json', $r->header('Content-Type'));
    }

    public function testHeaderIsCaseInsensitive(): void
    {
        $r = new HttpClientResponse(200, '', ['Content-Type' => 'text/html']);
        $this->assertSame('text/html', $r->header('content-type'));
        $this->assertSame('text/html', $r->header('CONTENT-TYPE'));
    }

    public function testHeaderReturnsNullWhenAbsent(): void
    {
        $r = new HttpClientResponse(200, '');
        $this->assertNull($r->header('X-Missing'));
    }

    public function testHeadersReturnsAll(): void
    {
        $headers = ['X-Foo' => 'bar', 'X-Baz' => 'qux'];
        $r       = new HttpClientResponse(200, '', $headers);
        $this->assertSame($headers, $r->headers());
    }

    // ------------------------------------------------------------------
    // throw()
    // ------------------------------------------------------------------

    public function testThrowDoesNothingOnSuccess(): void
    {
        $r = new HttpClientResponse(200, 'ok');
        $this->assertSame($r, $r->throw());
    }

    public function testThrowRaisesOnClientError(): void
    {
        $this->expectException(HttpClientException::class);
        (new HttpClientResponse(404, 'Not Found'))->throw();
    }

    public function testThrowRaisesOnServerError(): void
    {
        $this->expectException(HttpClientException::class);
        (new HttpClientResponse(500, 'Server Error'))->throw();
    }

    public function testThrowExceptionCarriesResponse(): void
    {
        $r = new HttpClientResponse(422, '{"error":"invalid"}');

        try {
            $r->throw();
            $this->fail('Exception attendue');
        } catch (HttpClientException $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertSame($r, $e->getResponse());
        }
    }
}
