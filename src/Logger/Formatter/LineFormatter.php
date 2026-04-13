<?php

declare(strict_types=1);

namespace Framework\Logger\Formatter;

/**
 * Formate un record en ligne de texte.
 *
 * Sortie : [2026-04-13 17:05:58] app.ERROR: User {id} not found {"id":42}
 *
 * Interpolation PSR-3 : les {placeholder} sont remplacés par les valeurs du contexte.
 */
class LineFormatter
{
    public function __construct(private readonly string $dateFormat = 'Y-m-d H:i:s') {}

    public function format(array $record): string
    {
        $date    = $record['datetime']->format($this->dateFormat);
        $level   = strtoupper($record['level']);
        $message = $this->interpolate($record['message'], $record['context']);
        $context = empty($record['context'])
            ? ''
            : ' ' . json_encode($record['context'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "[{$date}] {$record['channel']}.{$level}: {$message}{$context}" . PHP_EOL;
    }

    /**
     * Remplace {placeholder} dans le message par les valeurs scalaires du contexte.
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replace);
    }
}
