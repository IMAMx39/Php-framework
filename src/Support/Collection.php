<?php

declare(strict_types=1);

namespace Framework\Support;

/**
 * Wrapper fluent et chaînable autour d'un tableau.
 *
 * Usage :
 *   collect($users)
 *       ->filter(fn($u) => $u->active)
 *       ->sortBy('name')
 *       ->map(fn($u) => $u->email)
 *       ->values();
 */
class Collection implements \Countable, \IteratorAggregate, \ArrayAccess, \JsonSerializable
{
    public function __construct(private array $items = []) {}

    public static function make(array $items = []): static
    {
        return new static($items);
    }

    // ------------------------------------------------------------------
    // Accès de base
    // ------------------------------------------------------------------

    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    // ------------------------------------------------------------------
    // Transformation — retournent une nouvelle Collection
    // ------------------------------------------------------------------

    public function filter(?callable $callback = null): static
    {
        return new static($callback
            ? array_values(array_filter($this->items, $callback))
            : array_values(array_filter($this->items))
        );
    }

    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->items));
    }

    public function flatMap(callable $callback): static
    {
        $result = [];
        foreach ($this->items as $key => $item) {
            $mapped = $callback($item, $key);
            foreach ((array) $mapped as $v) {
                $result[] = $v;
            }
        }
        return new static($result);
    }

    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }
        return $this;
    }

    public function tap(callable $callback): static
    {
        $callback($this);
        return $this;
    }

    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    // ------------------------------------------------------------------
    // Tri
    // ------------------------------------------------------------------

    public function sortBy(string|callable $key, bool $descending = false): static
    {
        $items = $this->items;

        usort($items, function ($a, $b) use ($key, $descending) {
            $va = is_callable($key) ? $key($a) : $this->getProperty($a, $key);
            $vb = is_callable($key) ? $key($b) : $this->getProperty($b, $key);
            return $descending ? $vb <=> $va : $va <=> $vb;
        });

        return new static($items);
    }

    public function sortByDesc(string|callable $key): static
    {
        return $this->sortBy($key, descending: true);
    }

    public function reverse(): static
    {
        return new static(array_reverse($this->items));
    }

    // ------------------------------------------------------------------
    // Recherche
    // ------------------------------------------------------------------

    public function first(?callable $callback = null): mixed
    {
        if ($callback === null) {
            return $this->items[0] ?? null;
        }

        foreach ($this->items as $item) {
            if ($callback($item)) {
                return $item;
            }
        }

        return null;
    }

    public function last(?callable $callback = null): mixed
    {
        if ($callback === null) {
            return !empty($this->items) ? end($this->items) : null;
        }

        $found = null;
        foreach ($this->items as $item) {
            if ($callback($item)) {
                $found = $item;
            }
        }

        return $found;
    }

    public function contains(mixed $callbackOrValue): bool
    {
        if (is_callable($callbackOrValue)) {
            foreach ($this->items as $item) {
                if ($callbackOrValue($item)) {
                    return true;
                }
            }
            return false;
        }

        return in_array($callbackOrValue, $this->items, strict: true);
    }

    public function some(callable $callback): bool
    {
        return $this->contains($callback);
    }

    public function every(callable $callback): bool
    {
        foreach ($this->items as $item) {
            if (!$callback($item)) {
                return false;
            }
        }
        return true;
    }

    // ------------------------------------------------------------------
    // Agrégats
    // ------------------------------------------------------------------

    public function sum(string|callable|null $key = null): float|int
    {
        return array_sum($this->extractValues($key));
    }

    public function avg(string|callable|null $key = null): float|int
    {
        $values = $this->extractValues($key);
        return empty($values) ? 0 : array_sum($values) / count($values);
    }

    public function min(string|callable|null $key = null): mixed
    {
        $values = $this->extractValues($key);
        return empty($values) ? null : min($values);
    }

    public function max(string|callable|null $key = null): mixed
    {
        $values = $this->extractValues($key);
        return empty($values) ? null : max($values);
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    // ------------------------------------------------------------------
    // Groupement / découpage
    // ------------------------------------------------------------------

    public function groupBy(string|callable $key): static
    {
        $groups = [];

        foreach ($this->items as $item) {
            $groupKey = is_callable($key) ? $key($item) : $this->getProperty($item, $key);
            $groups[$groupKey][] = $item;
        }

        return new static(array_map(fn ($g) => new static($g), $groups));
    }

    public function chunk(int $size): static
    {
        return new static(array_map(
            fn ($chunk) => new static($chunk),
            array_chunk($this->items, $size),
        ));
    }

    public function take(int $limit): static
    {
        return new static(array_slice($this->items, 0, $limit));
    }

    public function skip(int $count): static
    {
        return new static(array_values(array_slice($this->items, $count)));
    }

    public function flatten(): static
    {
        $result = [];
        array_walk_recursive($this->items, function ($item) use (&$result) {
            $result[] = $item instanceof self ? $item->all() : $item;
        });
        return new static($result);
    }

    // ------------------------------------------------------------------
    // Extraction / transformation
    // ------------------------------------------------------------------

    public function pluck(string $key): static
    {
        return new static(array_map(
            fn ($item) => $this->getProperty($item, $key),
            $this->items,
        ));
    }

    public function unique(string|callable|null $key = null): static
    {
        if ($key === null) {
            return new static(array_values(array_unique($this->items)));
        }

        $seen   = [];
        $result = [];

        foreach ($this->items as $item) {
            $v = is_callable($key) ? $key($item) : $this->getProperty($item, $key);

            if (!in_array($v, $seen, strict: true)) {
                $seen[]   = $v;
                $result[] = $item;
            }
        }

        return new static($result);
    }

    public function values(): static
    {
        return new static(array_values($this->items));
    }

    public function keys(): static
    {
        return new static(array_keys($this->items));
    }

    public function merge(array|self $items): static
    {
        return new static(array_merge(
            $this->items,
            $items instanceof self ? $items->all() : $items,
        ));
    }

    // ------------------------------------------------------------------
    // Conversion
    // ------------------------------------------------------------------

    public function toArray(): array
    {
        return array_map(
            fn ($item) => $item instanceof self ? $item->toArray() : $item,
            $this->items,
        );
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->jsonSerialize(), $flags);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    // ------------------------------------------------------------------
    // Interfaces
    // ------------------------------------------------------------------

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    // ------------------------------------------------------------------
    // Helpers internes
    // ------------------------------------------------------------------

    private function getProperty(mixed $item, string $key): mixed
    {
        if (is_array($item)) {
            return $item[$key] ?? null;
        }

        if (is_object($item)) {
            if (isset($item->$key)) {
                return $item->$key;
            }

            $getter = 'get' . ucfirst($key);
            if (method_exists($item, $getter)) {
                return $item->$getter();
            }
        }

        return null;
    }

    private function extractValues(string|callable|null $key): array
    {
        if ($key === null) {
            return $this->items;
        }

        return array_map(
            fn ($item) => is_callable($key) ? $key($item) : $this->getProperty($item, $key),
            $this->items,
        );
    }
}
