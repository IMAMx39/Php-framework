<?php

declare(strict_types=1);

namespace Framework\Debug\Collector;

use Framework\Debug\QueryLog;

class QueryCollector implements CollectorInterface
{
    public function __construct(private readonly QueryLog $log) {}

    public function getName(): string { return 'sql'; }

    public function getSummary(): string
    {
        $n  = $this->log->count();
        $ms = number_format($this->log->totalTime(), 2);

        return "{$n} " . ($n === 1 ? 'query' : 'queries') . "  {$ms} ms";
    }

    public function getPanel(): string
    {
        if ($this->log->count() === 0) {
            return '<p class="__db-empty">Aucune requête SQL.</p>';
        }

        $html = '';
        foreach ($this->log->getEntries() as $i => $entry) {
            $n      = $i + 1;
            $sql    = htmlspecialchars($entry['sql']);
            $ms     = number_format($entry['duration'], 2);
            $params = htmlspecialchars(json_encode($entry['params']));

            $html .= '<div class="__db-query">';
            $html .= '<div class="__db-query-head">';
            $html .= "<span class=\"__db-badge\">{$n}</span>";
            $html .= "<span class=\"__db-query-time\">{$ms} ms</span>";
            $html .= '</div>';
            $html .= "<pre class=\"__db-pre\">{$sql}</pre>";

            if (!empty($entry['params'])) {
                $html .= "<p class=\"__db-params\">Params : <code>{$params}</code></p>";
            }

            $html .= '</div>';
        }

        return $html;
    }
}
