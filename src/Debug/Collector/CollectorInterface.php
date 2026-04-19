<?php

declare(strict_types=1);

namespace Framework\Debug\Collector;

interface CollectorInterface
{
    /** Nom du panneau (ex: 'sql'). */
    public function getName(): string;

    /** Texte court affiché dans l'onglet (ex: '3 queries  4.5 ms'). */
    public function getSummary(): string;

    /** HTML complet du panneau déroulant. */
    public function getPanel(): string;
}
