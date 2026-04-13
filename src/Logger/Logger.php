<?php

declare(strict_types=1);

namespace Framework\Logger;

use Framework\Logger\Handler\HandlerInterface;

/**
 * Logger inspiré de PSR-3 / Monolog.
 *
 * Usage :
 *   $logger->addHandler(new FileHandler(base_path('var/logs/app.log')));
 *
 *   $logger->info('Utilisateur connecté', ['user_id' => 42]);
 *   $logger->error('Paiement échoué', ['exception' => $e]);
 *   $logger->debug('Query : {sql}', ['sql' => $sql]);  // interpolation PSR-3
 */
class Logger
{
    /** @var HandlerInterface[] */
    private array $handlers = [];

    public function __construct(private readonly string $channel = 'app') {}

    public function addHandler(HandlerInterface $handler): static
    {
        $this->handlers[] = $handler;
        return $this;
    }

    // ── API PSR-3 ──────────────────────────────────────────────────────

    public function emergency(string $message, array $context = []): void { $this->log(LogLevel::EMERGENCY, $message, $context); }
    public function alert(string $message, array $context = []): void     { $this->log(LogLevel::ALERT,     $message, $context); }
    public function critical(string $message, array $context = []): void  { $this->log(LogLevel::CRITICAL,  $message, $context); }
    public function error(string $message, array $context = []): void     { $this->log(LogLevel::ERROR,     $message, $context); }
    public function warning(string $message, array $context = []): void   { $this->log(LogLevel::WARNING,   $message, $context); }
    public function notice(string $message, array $context = []): void    { $this->log(LogLevel::NOTICE,    $message, $context); }
    public function info(string $message, array $context = []): void      { $this->log(LogLevel::INFO,      $message, $context); }
    public function debug(string $message, array $context = []): void     { $this->log(LogLevel::DEBUG,     $message, $context); }

    public function log(string $level, string $message, array $context = []): void
    {
        if (!isset(LogLevel::SEVERITY[$level])) {
            throw new \InvalidArgumentException("Niveau de log invalide : « {$level} ».");
        }

        // Sérialise les exceptions pour éviter de stocker des objets dans les logs
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $e = $context['exception'];
            $context['exception'] = [
                'class'   => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile() . ':' . $e->getLine(),
            ];
        }

        $record = [
            'level'    => $level,
            'message'  => $message,
            'context'  => $context,
            'datetime' => new \DateTimeImmutable(),
            'channel'  => $this->channel,
        ];

        foreach ($this->handlers as $handler) {
            $handler->handle($record);
        }
    }

    public function getChannel(): string { return $this->channel; }
}
