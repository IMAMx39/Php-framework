<?php

declare(strict_types=1);

namespace Framework\Container;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

class Container
{
    /** @var array<string, mixed> Closures ou noms de classe concrets */
    private array $bindings = [];

    /** @var array<string, mixed> Instances déjà résolues (singletons) */
    private array $instances = [];

    // ------------------------------------------------------------------
    // Enregistrement
    // ------------------------------------------------------------------

    /**
     * Lie une interface/clé à une implémentation (résolue à chaque appel).
     */
    public function bind(string $abstract, mixed $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Lie une interface/clé à une implémentation résolue une seule fois.
     */
    public function singleton(string $abstract, mixed $concrete): void
    {
        $this->bindings[$abstract] = function (Container $c) use ($abstract, $concrete) {
            if (!isset($this->instances[$abstract])) {
                $this->instances[$abstract] = is_callable($concrete)
                    ? $concrete($c)
                    : $c->make($concrete);
            }

            return $this->instances[$abstract];
        };
    }

    /**
     * Enregistre directement une instance déjà construite.
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    // ------------------------------------------------------------------
    // Résolution
    // ------------------------------------------------------------------

    /**
     * Résout et retourne une instance de $abstract avec autowiring.
     */
    public function make(string $abstract): mixed
    {
        // Instance déjà en cache
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Binding enregistré
        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract];

            return is_callable($concrete)
                ? $concrete($this)
                : $this->build($concrete);
        }

        // Autowiring direct sur la classe
        return $this->build($abstract);
    }

    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id])
            || isset($this->instances[$id])
            || class_exists($id);
    }

    // ------------------------------------------------------------------
    // Construction par réflexion
    // ------------------------------------------------------------------

    /**
     * Instancie $class en résolvant ses dépendances récursivement.
     */
    private function build(string $class): mixed
    {
        $reflector = new ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new ContainerException(
                "La classe [$class] n'est pas instanciable."
            );
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $dependencies = array_map(
            fn (ReflectionParameter $param) => $this->resolveParameter($param, $class),
            $constructor->getParameters(),
        );

        return $reflector->newInstanceArgs($dependencies);
    }

    private function resolveParameter(ReflectionParameter $param, string $class): mixed
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->make($type->getName());
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw new ContainerException(
            "Impossible de résoudre le paramètre \${$param->getName()} de la classe [$class]."
        );
    }
}
