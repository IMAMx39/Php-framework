<?php

declare(strict_types=1);

namespace Framework\Http\Client;

class HttpClientException extends \RuntimeException
{
    public function __construct(private readonly HttpClientResponse $response)
    {
        parent::__construct(
            "HTTP {$response->status()} — {$response->text()}",
            $response->status(),
        );
    }

    public function getResponse(): HttpClientResponse
    {
        return $this->response;
    }
}
