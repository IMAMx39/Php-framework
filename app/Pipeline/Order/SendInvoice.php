<?php

declare(strict_types=1);

namespace App\Pipeline\Order;

/**
 * Étape 3 — "Envoie" la facture (simulé ici sans vrai mailer).
 */
class SendInvoice
{
    public function handle(OrderData $order, callable $next): OrderData
    {
        // En vrai : $this->mailer->send(new InvoiceMail($order));
        $order->invoiceSent = true;

        return $next($order);
    }
}
