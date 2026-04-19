<?php

declare(strict_types=1);

namespace Framework\Debug;

class QueryLog
{
    /** @var array<int, array{sql: string, params: array, duration: float}> */
    private array $entries = [];

    public function record(string $sql, array $params, float $durationMs): void
    {
        $this->entries[] = ['sql' => $sql, 'params' => $params, 'duration' => $durationMs];
    }

    /** @return array<int, array{sql: string, params: array, duration: float}> */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function totalTime(): float
    {
        return array_sum(array_column($this->entries, 'duration'));
    }
}
