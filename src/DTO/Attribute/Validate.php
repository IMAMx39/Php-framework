<?php

declare(strict_types=1);

namespace Framework\DTO\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Validate
{
    public function __construct(public readonly string $rules) {}
}
