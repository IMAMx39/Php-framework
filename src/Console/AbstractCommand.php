<?php

declare(strict_types=1);

namespace Framework\Console;

/**
 * Classe de base avec des helpers d'affichage pour les commandes CLI.
 */
abstract class AbstractCommand implements CommandInterface
{
    // ------------------------------------------------------------------
    // Affichage
    // ------------------------------------------------------------------

    protected function info(string $message): void
    {
        echo "\033[32m$message\033[0m" . PHP_EOL;
    }

    protected function error(string $message): void
    {
        echo "\033[31m$message\033[0m" . PHP_EOL;
    }

    protected function warning(string $message): void
    {
        echo "\033[33m$message\033[0m" . PHP_EOL;
    }

    protected function line(string $message = ''): void
    {
        echo $message . PHP_EOL;
    }

    protected function title(string $message): void
    {
        $len  = mb_strlen($message) + 4;
        $bar  = str_repeat('─', $len);
        echo PHP_EOL;
        echo "\033[36m┌{$bar}┐\033[0m" . PHP_EOL;
        echo "\033[36m│  \033[1m{$message}\033[0m\033[36m  │\033[0m" . PHP_EOL;
        echo "\033[36m└{$bar}┘\033[0m" . PHP_EOL;
        echo PHP_EOL;
    }

    protected function table(array $headers, array $rows): void
    {
        // Calcule la largeur de chaque colonne
        $widths = array_map('mb_strlen', $headers);

        foreach ($rows as $row) {
            foreach (array_values($row) as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, mb_strlen((string) $cell));
            }
        }

        $separator = '+-' . implode('-+-', array_map(fn ($w) => str_repeat('-', $w), $widths)) . '-+';

        $this->line($separator);
        $this->line('| ' . implode(' | ', array_map(
            fn ($h, $w) => "\033[1m" . str_pad($h, $w) . "\033[0m",
            $headers,
            $widths,
        )) . ' |');
        $this->line($separator);

        foreach ($rows as $row) {
            $cells = array_values($row);
            $this->line('| ' . implode(' | ', array_map(
                fn ($cell, $w) => str_pad((string) $cell, $w),
                $cells,
                $widths,
            )) . ' |');
        }

        $this->line($separator);
    }
}
