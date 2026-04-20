<?php

declare(strict_types=1);

use Framework\Support\Collection;

if (!function_exists('collect')) {
    function collect(array $items = []): Collection
    {
        return new Collection($items);
    }
}
