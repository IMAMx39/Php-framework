<?php

declare(strict_types=1);

namespace App\Pipeline\Order;

/**
 * Étape 2 — Applique une réduction si la commande dépasse 100 €.
 */
class ApplyDiscount
{
    private const THRESHOLD    = 100.0;
    private const DISCOUNT_PCT = 0.10; // -10 %

    public function handle(OrderData $order, callable $next): OrderData
    {
        if ($order->total >= self::THRESHOLD) {
            $order->total      *= (1 - self::DISCOUNT_PCT);
            $order->discounted  = true;
        }

        return $next($order);
    }
}
