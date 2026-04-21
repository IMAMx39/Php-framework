<?php

declare(strict_types=1);

namespace Tests\Unit\Pipeline;

use App\Pipeline\Order\ApplyDiscount;
use App\Pipeline\Order\OrderData;
use App\Pipeline\Order\SendInvoice;
use App\Pipeline\Order\ValidateStock;
use Framework\Exception\HttpException;
use Framework\Pipeline\Pipeline;
use PHPUnit\Framework\TestCase;

class PipelineTest extends TestCase
{
    // ------------------------------------------------------------------
    // Exemple complet — commande e-commerce
    // ------------------------------------------------------------------

    private function makeOrder(int $qty, float $unit): OrderData
    {
        return new OrderData(userId: 1, product: 'Widget', quantity: $qty, unitPrice: $unit);
    }

    public function testFullOrderPipelineSuccess(): void
    {
        $order = Pipeline::send($this->makeOrder(qty: 2, unit: 60.0))
            ->through([ValidateStock::class, ApplyDiscount::class, SendInvoice::class])
            ->thenReturn();

        $this->assertTrue($order->stockOk);
        $this->assertTrue($order->discounted);       // 2 × 60 = 120 > 100 → -10 %
        $this->assertEqualsWithDelta(108.0, $order->total, 0.001);
        $this->assertTrue($order->invoiceSent);
    }

    public function testNoDiscountBelowThreshold(): void
    {
        $order = Pipeline::send($this->makeOrder(qty: 1, unit: 50.0))
            ->through([ValidateStock::class, ApplyDiscount::class, SendInvoice::class])
            ->thenReturn();

        $this->assertFalse($order->discounted);
        $this->assertEqualsWithDelta(50.0, $order->total, 0.001);
    }

    public function testStockValidationShortCircuits(): void
    {
        $this->expectException(HttpException::class);

        // Quantité 99 > stock 10 → exception, les étapes suivantes ne tournent pas
        Pipeline::send($this->makeOrder(qty: 99, unit: 10.0))
            ->through([ValidateStock::class, ApplyDiscount::class, SendInvoice::class])
            ->thenReturn();
    }

    public function testStepsAfterShortCircuitAreNotExecuted(): void
    {
        $order = $this->makeOrder(qty: 99, unit: 10.0);

        try {
            Pipeline::send($order)
                ->through([ValidateStock::class, ApplyDiscount::class, SendInvoice::class])
                ->thenReturn();
        } catch (HttpException) {}

        // Les étapes suivantes n'ont pas été atteintes
        $this->assertFalse($order->discounted);
        $this->assertFalse($order->invoiceSent);
    }

    // ------------------------------------------------------------------
    // Comportements génériques du Pipeline
    // ------------------------------------------------------------------

    public function testClosurePipe(): void
    {
        $result = Pipeline::send(5)
            ->through([
                fn ($v, $next) => $next($v * 2),   // 5 → 10
                fn ($v, $next) => $next($v + 3),   // 10 → 13
            ])
            ->thenReturn();

        $this->assertSame(13, $result);
    }

    public function testObjectPipe(): void
    {
        $pipe = new class {
            public function handle(string $v, callable $next): string
            {
                return $next(strtoupper($v));
            }
        };

        $result = Pipeline::send('hello')->through([$pipe])->thenReturn();
        $this->assertSame('HELLO', $result);
    }

    public function testEmptyPipelineReturnsSameValue(): void
    {
        $result = Pipeline::send('unchanged')->through([])->thenReturn();
        $this->assertSame('unchanged', $result);
    }

    public function testThenWithDestination(): void
    {
        $result = Pipeline::send(10)
            ->through([fn ($v, $n) => $n($v + 5)])
            ->then(fn ($v) => $v * 2);

        $this->assertSame(30, $result);
    }

    public function testPipeMethodAddsStep(): void
    {
        $result = Pipeline::send(1)
            ->through([fn ($v, $n) => $n($v + 1)])
            ->pipe(fn ($v, $n) => $n($v * 10))
            ->thenReturn();

        $this->assertSame(20, $result);
    }

    public function testThroughIsImmutable(): void
    {
        $base = Pipeline::send('x')
            ->through([fn ($v, $n) => $n($v . 'A')]);

        $extended = $base->pipe(fn ($v, $n) => $n($v . 'B'));

        // $base ne connaît pas l'étape B
        $this->assertSame('xA',  $base->thenReturn());
        $this->assertSame('xAB', $extended->thenReturn());
    }

    public function testViaCustomMethod(): void
    {
        $pipe = new class {
            public function process(int $v, callable $next): int
            {
                return $next($v + 100);
            }
        };

        $result = Pipeline::send(1)
            ->through([$pipe])
            ->via('process')
            ->thenReturn();

        $this->assertSame(101, $result);
    }

    public function testStepsExecuteInOrder(): void
    {
        $log = [];

        Pipeline::send('start')
            ->through([
                function ($v, $next) use (&$log) { $log[] = 1; return $next($v); },
                function ($v, $next) use (&$log) { $log[] = 2; return $next($v); },
                function ($v, $next) use (&$log) { $log[] = 3; return $next($v); },
            ])
            ->thenReturn();

        $this->assertSame([1, 2, 3], $log);
    }
}
