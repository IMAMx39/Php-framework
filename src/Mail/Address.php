<?php

declare(strict_types=1);

namespace Framework\Mail;

/**
 * Représente une adresse email avec un nom d'affichage optionnel.
 */
final class Address
{
    public function __construct(
        public readonly string $email,
        public readonly string $name = '',
    ) {}

    /**
     * Formate l'adresse pour les en-têtes RFC 2822.
     * Ex: "John Doe <john@example.com>" ou "john@example.com"
     */
    public function format(): string
    {
        if ($this->name === '') {
            return $this->email;
        }

        return sprintf('"%s" <%s>', addslashes($this->name), $this->email);
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
