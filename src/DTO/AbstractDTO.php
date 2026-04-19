<?php

declare(strict_types=1);

namespace Framework\DTO;

use Framework\DTO\Attribute\Validate;
use Framework\Http\Request;
use Framework\Validation\Validator;

abstract class AbstractDTO
{
    /**
     * Construit le DTO depuis les données de la requête (JSON ou form).
     * Valide et caste automatiquement selon les types PHP déclarés.
     */
    public static function fromRequest(Request $request): static
    {
        $data = $request->isJson()
            ? (array) $request->json()
            : $request->all();

        return static::fromArray($data);
    }

    /**
     * Construit le DTO depuis un tableau de données brutes.
     */
    public static function fromArray(array $data): static
    {
        $ref         = new \ReflectionClass(static::class);
        $constructor = $ref->getConstructor();

        if ($constructor === null) {
            return new static();
        }

        $rules = static::extractRules($constructor);

        if (!empty($rules)) {
            Validator::make($data, $rules);
        }

        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $name  = $param->getName();
            $type  = $param->getType();
            $value = array_key_exists($name, $data) ? $data[$name] : null;

            if ($value === null) {
                $args[] = $param->isDefaultValueAvailable()
                    ? $param->getDefaultValue()
                    : null;
                continue;
            }

            $args[] = $type instanceof \ReflectionNamedType
                ? static::cast($value, $type)
                : $value;
        }

        return new static(...$args);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private static function extractRules(\ReflectionMethod $constructor): array
    {
        $rules = [];

        foreach ($constructor->getParameters() as $param) {
            $attrs = $param->getAttributes(Validate::class);

            if (!empty($attrs)) {
                $rules[$param->getName()] = $attrs[0]->newInstance()->rules;
            }
        }

        return $rules;
    }

    private static function cast(mixed $value, \ReflectionNamedType $type): mixed
    {
        if (!$type->isBuiltin()) {
            $class = $type->getName();

            if ($class === \DateTimeImmutable::class) {
                return new \DateTimeImmutable((string) $value);
            }

            if ($class === \DateTime::class) {
                return new \DateTime((string) $value);
            }

            return $value;
        }

        return match ($type->getName()) {
            'int'    => (int) $value,
            'float'  => (float) $value,
            'bool'   => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            'string' => (string) $value,
            'array'  => (array) $value,
            default  => $value,
        };
    }
}
