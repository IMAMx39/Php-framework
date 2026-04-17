<?php

declare(strict_types=1);

namespace Framework\Mail;

/**
 * Mailer utilisant la fonction mail() de PHP.
 * Pratique pour un hébergement mutualisé ou un serveur avec sendmail configuré.
 *
 * Limitation : pas de gestion TLS, authentification SMTP ou pièces jointes avancées.
 * Pour un usage en production, préférer SmtpMailer.
 */
class NativeMailer implements MailerInterface
{
    public function send(Message $message): void
    {
        $message->validate();

        $to      = implode(', ', array_map(fn (Address $a) => $a->format(), $message->getTo()));
        $subject = $this->encodeHeader($message->getSubject());
        $headers = $this->buildHeaders($message);
        $body    = $this->buildBody($message);

        if (!mail($to, $subject, $body, $headers)) {
            throw new \RuntimeException("Échec de l'envoi via mail().");
        }
    }

    // ------------------------------------------------------------------

    private function buildHeaders(Message $message): string
    {
        $h = [];

        $h[] = 'From: ' . $message->getFrom()->format();
        $h[] = 'MIME-Version: 1.0';

        if (!empty($message->getCc())) {
            $h[] = 'Cc: ' . implode(', ', array_map(fn ($a) => $a->format(), $message->getCc()));
        }

        if (!empty($message->getBcc())) {
            $h[] = 'Bcc: ' . implode(', ', array_map(fn ($a) => $a->format(), $message->getBcc()));
        }

        if ($message->getHtml() !== null && $message->getText() !== null) {
            $boundary = '----=_Part_' . md5(uniqid('', true));
            $h[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        } elseif ($message->getHtml() !== null) {
            $h[] = 'Content-Type: text/html; charset=UTF-8';
            $h[] = 'Content-Transfer-Encoding: base64';
        } else {
            $h[] = 'Content-Type: text/plain; charset=UTF-8';
            $h[] = 'Content-Transfer-Encoding: base64';
        }

        return implode("\r\n", $h);
    }

    private function buildBody(Message $message): string
    {
        if ($message->getHtml() !== null && $message->getText() !== null) {
            $boundary = '----=_Part_' . md5(uniqid('', true));

            return implode("\r\n", [
                "--{$boundary}",
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode($message->getText())),
                "--{$boundary}",
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode($message->getHtml())),
                "--{$boundary}--",
            ]);
        }

        $content = $message->getHtml() ?? $message->getText() ?? '';

        return chunk_split(base64_encode($content));
    }

    private function encodeHeader(string $value): string
    {
        if (mb_detect_encoding($value, 'ASCII', true)) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
