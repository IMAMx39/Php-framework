<?php

declare(strict_types=1);

namespace Framework\Logger;

/**
 * Niveaux de log RFC 5424 — compatibles PSR-3.
 * Ordre de sévérité : DEBUG < INFO < NOTICE < WARNING < ERROR < CRITICAL < ALERT < EMERGENCY
 */
final class LogLevel
{
    public const DEBUG     = 'debug';
    public const INFO      = 'info';
    public const NOTICE    = 'notice';
    public const WARNING   = 'warning';
    public const ERROR     = 'error';
    public const CRITICAL  = 'critical';
    public const ALERT     = 'alert';
    public const EMERGENCY = 'emergency';

    public const SEVERITY = [
        self::DEBUG     => 100,
        self::INFO      => 200,
        self::NOTICE    => 250,
        self::WARNING   => 300,
        self::ERROR     => 400,
        self::CRITICAL  => 500,
        self::ALERT     => 550,
        self::EMERGENCY => 600,
    ];

    /** Retourne true si $level est au moins aussi grave que $minLevel. */
    public static function isHandling(string $level, string $minLevel): bool
    {
        return (self::SEVERITY[$level] ?? 0) >= (self::SEVERITY[$minLevel] ?? 0);
    }
}
