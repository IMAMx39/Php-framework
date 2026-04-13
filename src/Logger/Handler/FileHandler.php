<?php

declare(strict_types=1);

namespace Framework\Logger\Handler;

use Framework\Logger\Formatter\LineFormatter;
use Framework\Logger\LogLevel;

/**
 * Écrit les logs dans un fichier texte.
 *
 * Usage :
 *   $logger->addHandler(new FileHandler('/var/logs/app.log'));
 *   $logger->addHandler(new FileHandler('/var/logs/error.log', LogLevel::ERROR));
 */
class FileHandler implements HandlerInterface
{
    private LineFormatter $formatter;

    public function __construct(
        private readonly string $path,
        private readonly string $minLevel = LogLevel::DEBUG,
    ) {
        $this->formatter = new LineFormatter();

        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }
    }

    public function handle(array $record): void
    {
        if (!$this->isHandling($record['level'])) {
            return;
        }

        file_put_contents($this->path, $this->formatter->format($record), FILE_APPEND | LOCK_EX);
    }

    public function isHandling(string $level): bool
    {
        return LogLevel::isHandling($level, $this->minLevel);
    }
}
