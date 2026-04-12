<?php

declare(strict_types=1);

namespace Framework\Exception;

class MethodNotAllowedException extends HttpException
{
    public function __construct(string $message = 'Méthode non autorisée.', ?\Throwable $previous = null)
    {
        parent::__construct(405, $message, $previous);
    }
}
