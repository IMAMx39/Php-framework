<?php

declare(strict_types=1);

namespace Framework\Debug\Collector;

class MemoryCollector implements CollectorInterface
{
    public function getName(): string { return 'memory'; }

    public function getSummary(): string
    {
        return $this->format(memory_get_peak_usage(true));
    }

    public function getPanel(): string
    {
        $rows = [
            ['Peak (réelle)',   $this->format(memory_get_peak_usage(true))],
            ['Peak (allouée)',  $this->format(memory_get_peak_usage(false))],
            ['Actuelle',        $this->format(memory_get_usage(true))],
            ['Limite PHP',      ini_get('memory_limit')],
        ];

        $html = '<table class="__db-table"><tbody>';
        foreach ($rows as [$label, $value]) {
            $html .= "<tr><th>{$label}</th><td>{$value}</td></tr>";
        }

        return $html . '</tbody></table>';
    }

    private function format(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return "{$bytes} B";
    }
}
