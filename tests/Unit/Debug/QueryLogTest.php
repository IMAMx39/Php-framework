<?php

declare(strict_types=1);

namespace Tests\Unit\Debug;

use Framework\Debug\QueryLog;
use PHPUnit\Framework\TestCase;

class QueryLogTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        $log = new QueryLog();
        $this->assertSame(0, $log->count());
        $this->assertSame([], $log->getEntries());
        $this->assertSame(0.0, $log->totalTime());
    }

    public function testRecordStoresEntry(): void
    {
        $log = new QueryLog();
        $log->record('SELECT 1', [], 1.5);

        $this->assertSame(1, $log->count());
        $this->assertSame('SELECT 1', $log->getEntries()[0]['sql']);
        $this->assertSame(1.5, $log->getEntries()[0]['duration']);
    }

    public function testCountIncrements(): void
    {
        $log = new QueryLog();
        $log->record('SELECT 1', [], 1.0);
        $log->record('SELECT 2', [], 2.0);

        $this->assertSame(2, $log->count());
    }

    public function testTotalTimeIsSum(): void
    {
        $log = new QueryLog();
        $log->record('SELECT 1', [], 1.5);
        $log->record('SELECT 2', [], 2.5);

        $this->assertSame(4.0, $log->totalTime());
    }

    public function testParamsAreStored(): void
    {
        $log = new QueryLog();
        $log->record('SELECT * FROM users WHERE id = ?', [42], 0.5);

        $this->assertSame([42], $log->getEntries()[0]['params']);
    }
}
