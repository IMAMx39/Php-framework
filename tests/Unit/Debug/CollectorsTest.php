<?php

declare(strict_types=1);

namespace Tests\Unit\Debug;

use Framework\Debug\Collector\LogCollector;
use Framework\Debug\Collector\MemoryCollector;
use Framework\Debug\Collector\QueryCollector;
use Framework\Debug\Collector\RequestCollector;
use Framework\Debug\QueryLog;
use Framework\Http\Request;
use PHPUnit\Framework\TestCase;

class CollectorsTest extends TestCase
{
    // ------------------------------------------------------------------
    // QueryCollector
    // ------------------------------------------------------------------

    public function testQueryCollectorNameIsSql(): void
    {
        $c = new QueryCollector(new QueryLog());
        $this->assertSame('sql', $c->getName());
    }

    public function testQueryCollectorSummaryWithNoQueries(): void
    {
        $c = new QueryCollector(new QueryLog());
        $this->assertStringContainsString('0', $c->getSummary());
    }

    public function testQueryCollectorSummaryCountsQueries(): void
    {
        $log = new QueryLog();
        $log->record('SELECT 1', [], 2.0);
        $log->record('SELECT 2', [], 3.0);

        $c = new QueryCollector($log);
        $this->assertStringContainsString('2', $c->getSummary());
        $this->assertStringContainsString('5.00', $c->getSummary());
    }

    public function testQueryCollectorPanelShowsEmptyMessageWhenNoQueries(): void
    {
        $c = new QueryCollector(new QueryLog());
        $this->assertStringContainsString('Aucune', $c->getPanel());
    }

    public function testQueryCollectorPanelShowsSql(): void
    {
        $log = new QueryLog();
        $log->record('SELECT * FROM users', [], 1.0);

        $c = new QueryCollector($log);
        $this->assertStringContainsString('SELECT * FROM users', $c->getPanel());
    }

    // ------------------------------------------------------------------
    // MemoryCollector
    // ------------------------------------------------------------------

    public function testMemoryCollectorNameIsMemory(): void
    {
        $c = new MemoryCollector();
        $this->assertSame('memory', $c->getName());
    }

    public function testMemoryCollectorSummaryContainsUnit(): void
    {
        $summary = (new MemoryCollector())->getSummary();
        $this->assertMatchesRegularExpression('/\d+(\.\d+)?\s+(B|KB|MB)/', $summary);
    }

    public function testMemoryCollectorPanelContainsTable(): void
    {
        $panel = (new MemoryCollector())->getPanel();
        $this->assertStringContainsString('<table', $panel);
        $this->assertStringContainsString('Peak', $panel);
    }

    // ------------------------------------------------------------------
    // LogCollector
    // ------------------------------------------------------------------

    public function testLogCollectorNameIsLogs(): void
    {
        $c = new LogCollector();
        $this->assertSame('logs', $c->getName());
    }

    public function testLogCollectorCapturesRecords(): void
    {
        $c = new LogCollector();
        $c->handle(['level' => 'info', 'message' => 'hello', 'context' => []]);

        $this->assertStringContainsString('1', $c->getSummary());
        $this->assertStringContainsString('hello', $c->getPanel());
    }

    public function testLogCollectorPanelShowsEmptyMessage(): void
    {
        $c = new LogCollector();
        $this->assertStringContainsString('Aucun', $c->getPanel());
    }

    public function testLogCollectorIsHandlingAnyLevel(): void
    {
        $c = new LogCollector();
        $this->assertTrue($c->isHandling('info'));
        $this->assertTrue($c->isHandling('error'));
        $this->assertTrue($c->isHandling('debug'));
    }

    // ------------------------------------------------------------------
    // RequestCollector
    // ------------------------------------------------------------------

    public function testRequestCollectorNameIsRequest(): void
    {
        $request = Request::fromGlobals();
        $c       = new RequestCollector($request);
        $this->assertSame('request', $c->getName());
    }

    public function testRequestCollectorSummaryContainsMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = Request::fromGlobals();
        $c       = new RequestCollector($request);

        $this->assertStringContainsString('POST', $c->getSummary());
    }

    public function testRequestCollectorPanelContainsUrl(): void
    {
        $_SERVER['REQUEST_URI']    = '/api/test';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = Request::fromGlobals();
        $c       = new RequestCollector($request);

        $this->assertStringContainsString('/api/test', $c->getPanel());
    }
}
