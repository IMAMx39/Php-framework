<?php

declare(strict_types=1);

namespace Framework\Routing;

class Route
{
    private array $parameters = [];

    public function __construct(
        private readonly string $path,
        private readonly array $methods,
        private readonly mixed $handler,
        private readonly ?string $name = null,
    ) {}

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getHandler(): mixed
    {
        return $this->handler;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * Convertit le chemin avec paramètres dynamiques en regex.
     * Ex: /users/{id} => #^/users/(?P<id>[^/]+)$#
     */
    public function buildPattern(): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $this->path);

        return '#^' . $pattern . '$#';
    }
}
