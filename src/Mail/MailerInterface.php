<?php

declare(strict_types=1);

namespace Framework\Mail;

interface MailerInterface
{
    /**
     * Envoie un email.
     *
     * @throws \RuntimeException    En cas d'erreur d'envoi.
     * @throws \LogicException      Si le message est incomplet.
     */
    public function send(Message $message): void;
}
