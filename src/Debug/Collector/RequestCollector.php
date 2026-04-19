<?php

declare(strict_types=1);

namespace Framework\Debug\Collector;

use Framework\Http\Request;
use Framework\Http\Response;

class RequestCollector implements CollectorInterface
{
    private float $startTime;

    public function __construct(private readonly Request $request)
    {
        $this->startTime = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    }

    public function getName(): string { return 'request'; }

    public function getSummary(): string
    {
        return $this->request->getMethod() . '  ' . $this->durationMs() . ' ms';
    }

    public function getPanel(): string
    {
        $method  = htmlspecialchars($this->request->getMethod());
        $uri     = htmlspecialchars($this->request->getUri());
        $ip      = htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ms      = $this->durationMs();

        $rows = [
            ['Méthode',  $method],
            ['URL',      $uri],
            ['IP',       $ip],
            ['Durée',    "{$ms} ms"],
        ];

        return $this->table($rows);
    }

    private function durationMs(): string
    {
        return number_format((microtime(true) - $this->startTime) * 1000, 2);
    }

    private function table(array $rows): string
    {
        $html = '<table class="__db-table"><tbody>';
        foreach ($rows as [$label, $value]) {
            $html .= "<tr><th>{$label}</th><td>{$value}</td></tr>";
        }
        return $html . '</tbody></table>';
    }
}
