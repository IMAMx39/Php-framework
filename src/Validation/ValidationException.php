<?php

declare(strict_types=1);

namespace Framework\Validation;

use Framework\Exception\HttpException;

class ValidationException extends HttpException
{
    /**
     * @param array<string, string[]> $errors Erreurs par champ.
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(422, 'Les données fournies sont invalides.');
    }

    /**
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
