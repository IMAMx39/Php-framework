<?php

declare(strict_types=1);

namespace App\Pipeline\Order;

/**
 * Objet qui transite dans le pipeline de commande.
 * Chaque étape peut lire et modifier ses propriétés.
 */
class OrderData
{
    public float  $total;
    public bool   $stockOk    = false;
    public bool   $discounted = false;
    public bool   $invoiceSent = false;

    public function __construct(
        public readonly int    $userId,
        public readonly string $product,
        public readonly int    $quantity,
        public readonly float  $unitPrice,
    ) {
        $this->total = $quantity * $unitPrice;
    }
}
