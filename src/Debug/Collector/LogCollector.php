<?php

declare(strict_types=1);

namespace Framework\Debug\Collector;

use Framework\Logger\Handler\HandlerInterface;
use Framework\Logger\LogLevel;

class LogCollector implements CollectorInterface, HandlerInterface
{
    /** @var array<int, array{level: string, message: string, context: array}> */
    private array $records = [];

    // ------------------------------------------------------------------
    // CollectorInterface
    // ------------------------------------------------------------------

    public function getName(): string { return 'logs'; }

    public function getSummary(): string
    {
        $n = count($this->records);
        return $n . ' ' . ($n === 1 ? 'log' : 'logs');
    }

    public function getPanel(): string
    {
        if (empty($this->records)) {
            return '<p class="__db-empty">Aucun log.</p>';
        }

        $html = '';
        foreach ($this->records as $record) {
            $level   = htmlspecialchars($record['level']);
            $message = htmlspecialchars($record['message']);
            $context = !empty($record['context'])
                ? '<code>' . htmlspecialchars(json_encode($record['context'])) . '</code>'
                : '';

            $html .= <<<HTML
            <div class="__db-log __db-log-{$level}">
                <span class="__db-badge __db-badge-{$level}">{$level}</span>
                <span class="__db-log-msg">{$message}</span>
                {$context}
            </div>
            HTML;
        }

        return $html;
    }

    // ------------------------------------------------------------------
    // HandlerInterface — branché sur le Logger
    // ------------------------------------------------------------------

    public function handle(array $record): void
    {
        $this->records[] = [
            'level'   => $record['level'],
            'message' => $record['message'],
            'context' => $record['context'] ?? [],
        ];
    }

    public function isHandling(string $level): bool
    {
        return isset(LogLevel::SEVERITY[$level]);
    }
}
