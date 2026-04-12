<?php

declare(strict_types=1);

namespace Framework\Http;

class JsonResponse extends Response
{
    public function __construct(
        mixed $data = null,
        int $statusCode = 200,
        array $headers = [],
    ) {
        $headers['Content-Type'] = 'application/json';

        parent::__construct(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            $statusCode,
            $headers,
        );
    }

    public static function make(mixed $data = null, int $status = 200, array $headers = []): static
    {
        return new static($data, $status, $headers);
    }
}
