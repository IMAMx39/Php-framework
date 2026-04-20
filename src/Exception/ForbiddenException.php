<?php

declare(strict_types=1);

namespace Framework\Exception;

class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Accès interdit.')
    {
        parent::__construct(403, $message);
    }
}
