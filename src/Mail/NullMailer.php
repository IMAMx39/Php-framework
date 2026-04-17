<?php

declare(strict_types=1);

namespace Framework\Mail;

/**
 * Mailer no-op — absorbe tous les envois sans rien faire.
 * Idéal pour les tests et l'environnement de développement.
 *
 * Enregistrement :
 *   $container->singleton(MailerInterface::class, fn() => new NullMailer());
 */
class NullMailer implements MailerInterface
{
    /** @var Message[] */
    private array $sent = [];

    public function send(Message $message): void
    {
        $message->validate();
        $this->sent[] = $message;
    }

    /** @return Message[] Messages "envoyés" (utile dans les tests). */
    public function getSent(): array
    {
        return $this->sent;
    }

    public function getLastSent(): ?Message
    {
        return empty($this->sent) ? null : end($this->sent);
    }

    public function reset(): void
    {
        $this->sent = [];
    }
}
