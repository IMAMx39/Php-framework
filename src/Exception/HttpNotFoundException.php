<?php

declare(strict_types=1);

namespace Framework\Exception;

class HttpNotFoundException extends HttpException
{
    public function __construct(string $message = 'Page introuvable.', ?\Throwable $previous = null)
    {
        parent::__construct(404, $message, $previous);
    }
}
