<?php

declare(strict_types=1);

namespace App\Pipeline\Order;

use Framework\Exception\HttpException;

/**
 * Étape 1 — Vérifie que le stock est suffisant.
 * Court-circuite le pipeline si la quantité dépasse le stock disponible.
 */
class ValidateStock
{
    private const FAKE_STOCK = 10; // simule un stock de 10 unités

    public function handle(OrderData $order, callable $next): OrderData
    {
        if ($order->quantity > self::FAKE_STOCK) {
            throw new HttpException(422, "Stock insuffisant : {$order->quantity} demandés, " . self::FAKE_STOCK . " disponibles.");
        }

        $order->stockOk = true;

        return $next($order);
    }
}
