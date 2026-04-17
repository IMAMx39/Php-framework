<?php

declare(strict_types=1);

namespace Framework\Mail;

/**
 * Mailer SMTP via PHP streams (sans dépendance externe).
 *
 * Supporte :
 *   - Connexion plain / SSL / TLS (STARTTLS)
 *   - AUTH LOGIN (username + password)
 *   - Envoi multipart (text + html)
 *   - Pièces jointes (base64)
 *
 * Exemple de configuration :
 *   $mailer = new SmtpMailer(
 *       host:       'smtp.mailtrap.io',
 *       port:       2525,
 *       username:   'user',
 *       password:   'pass',
 *       encryption: 'tls',  // 'tls' | 'ssl' | 'none'
 *   );
 */
class SmtpMailer implements MailerInterface
{
    private const TIMEOUT = 15;

    public function __construct(
        private readonly string $host,
        private readonly int    $port       = 587,
        private readonly string $username   = '',
        private readonly string $password   = '',
        private readonly string $encryption = 'tls',
    ) {}

    public function send(Message $message): void
    {
        $message->validate();

        $socket = $this->connect();

        try {
            $this->smtp($socket, $message);
        } finally {
            $this->write($socket, "QUIT\r\n");
            fclose($socket);
        }
    }

    // ------------------------------------------------------------------
    // Connexion
    // ------------------------------------------------------------------

    /** @return resource */
    private function connect(): mixed
    {
        $scheme = $this->encryption === 'ssl' ? 'ssl' : 'tcp';
        $dsn    = "{$scheme}://{$this->host}:{$this->port}";

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
            ],
        ]);

        $socket = stream_socket_client(
            $dsn,
            $errno,
            $errstr,
            self::TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($socket === false) {
            throw new \RuntimeException("Connexion SMTP impossible ({$errno}): {$errstr}");
        }

        stream_set_timeout($socket, self::TIMEOUT);
        $this->expect($socket, 220);

        return $socket;
    }

    // ------------------------------------------------------------------
    // Dialogue SMTP
    // ------------------------------------------------------------------

    /** @param resource $s */
    private function smtp(mixed $s, Message $message): void
    {
        // EHLO
        $this->write($s, "EHLO {$this->host}\r\n");
        $capabilities = $this->readAll($s, 250);

        // STARTTLS
        if ($this->encryption === 'tls') {
            if (!str_contains($capabilities, 'STARTTLS')) {
                throw new \RuntimeException('Le serveur SMTP ne supporte pas STARTTLS.');
            }

            $this->write($s, "STARTTLS\r\n");
            $this->expect($s, 220);

            if (!stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('Échec de la négociation TLS.');
            }

            // Ré-EHLO après TLS
            $this->write($s, "EHLO {$this->host}\r\n");
            $this->readAll($s, 250);
        }

        // AUTH LOGIN
        if ($this->username !== '') {
            $this->write($s, "AUTH LOGIN\r\n");
            $this->expect($s, 334);
            $this->write($s, base64_encode($this->username) . "\r\n");
            $this->expect($s, 334);
            $this->write($s, base64_encode($this->password) . "\r\n");
            $this->expect($s, 235);
        }

        // Enveloppe
        $this->write($s, "MAIL FROM:<{$message->getFrom()->email}>\r\n");
        $this->expect($s, 250);

        foreach ($message->getTo() as $addr) {
            $this->write($s, "RCPT TO:<{$addr->email}>\r\n");
            $this->expect($s, 250);
        }

        foreach ($message->getCc() as $addr) {
            $this->write($s, "RCPT TO:<{$addr->email}>\r\n");
            $this->expect($s, 250);
        }

        foreach ($message->getBcc() as $addr) {
            $this->write($s, "RCPT TO:<{$addr->email}>\r\n");
            $this->expect($s, 250);
        }

        // Corps
        $this->write($s, "DATA\r\n");
        $this->expect($s, 354);
        $this->write($s, $this->buildRaw($message) . "\r\n.\r\n");
        $this->expect($s, 250);
    }

    // ------------------------------------------------------------------
    // Construction du message brut RFC 2822
    // ------------------------------------------------------------------

    private function buildRaw(Message $message): string
    {
        $to = implode(', ', array_map(fn ($a) => $a->format(), $message->getTo()));
        $cc = implode(', ', array_map(fn ($a) => $a->format(), $message->getCc()));

        $boundary     = '----=_Part_' . bin2hex(random_bytes(8));
        $hasMultipart = $message->getHtml() !== null && $message->getText() !== null;
        $hasAttach    = !empty($message->getAttachments());

        $headers  = "From: {$message->getFrom()->format()}\r\n";
        $headers .= "To: {$to}\r\n";
        if ($cc) $headers .= "Cc: {$cc}\r\n";
        $headers .= "Subject: {$this->encodeHeader($message->getSubject())}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Date: " . date('r') . "\r\n";

        if (!$hasAttach && !$hasMultipart) {
            // Corps simple
            if ($message->getHtml() !== null) {
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $headers .= chunk_split(base64_encode($message->getHtml()));
            } else {
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $headers .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $headers .= chunk_split(base64_encode($message->getText() ?? ''));
            }

            return $headers;
        }

        // Multipart
        $outerBoundary = $hasAttach ? '----=_Outer_' . bin2hex(random_bytes(8)) : $boundary;

        if ($hasAttach) {
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$outerBoundary}\"\r\n\r\n";
        } else {
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
        }

        $body = '';

        if ($hasAttach && $hasMultipart) {
            $body .= "--{$outerBoundary}\r\n";
            $body .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
        }

        if ($hasMultipart) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($message->getText() ?? '')) . "\r\n";

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($message->getHtml() ?? '')) . "\r\n";

            $body .= "--{$boundary}--\r\n";
        } elseif ($message->getHtml() !== null) {
            $part = $hasAttach ? $outerBoundary : $boundary;
            $body .= "--{$part}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($message->getHtml())) . "\r\n";
        } else {
            $part = $hasAttach ? $outerBoundary : $boundary;
            $body .= "--{$part}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($message->getText() ?? '')) . "\r\n";
        }

        foreach ($message->getAttachments() as $name => $path) {
            $content = file_get_contents($path);
            if ($content === false) continue;

            $mime = mime_content_type($path) ?: 'application/octet-stream';

            $body .= "--{$outerBoundary}\r\n";
            $body .= "Content-Type: {$mime}; name=\"{$name}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n";
            $body .= chunk_split(base64_encode($content)) . "\r\n";
        }

        if ($hasAttach) {
            $body .= "--{$outerBoundary}--\r\n";
        }

        return $headers . $body;
    }

    // ------------------------------------------------------------------
    // I/O bas niveau
    // ------------------------------------------------------------------

    /** @param resource $s */
    private function write(mixed $s, string $data): void
    {
        fwrite($s, $data);
    }

    /** @param resource $s */
    private function read(mixed $s): string
    {
        return (string) fgets($s, 512);
    }

    /** @param resource $s */
    private function readAll(mixed $s, int $expectedCode): string
    {
        $all = '';
        do {
            $line = $this->read($s);
            $all .= $line;
            $code = (int) substr($line, 0, 3);
            $more = isset($line[3]) && $line[3] === '-';

            if ($code !== $expectedCode && !$more) {
                throw new \RuntimeException("SMTP: réponse inattendue [{$code}]: {$line}");
            }
        } while ($more);

        return $all;
    }

    /** @param resource $s */
    private function expect(mixed $s, int $code): string
    {
        return $this->readAll($s, $code);
    }

    private function encodeHeader(string $value): string
    {
        if (mb_detect_encoding($value, 'ASCII', true)) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
