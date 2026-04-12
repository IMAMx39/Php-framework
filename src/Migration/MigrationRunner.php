<?php

declare(strict_types=1);

namespace Framework\Migration;

use Framework\Database\Connection;

/**
 * Gère l'exécution, le rollback et le statut des migrations.
 *
 * Convention de nommage des fichiers :
 *   Version{YYYYMMDDHHMMSS}{Description}.php
 *   Ex: Version20260412120000CreateUsersTable.php
 *
 * Les fichiers sont triés alphabétiquement → ordre chronologique garanti
 * si le timestamp est bien en tête du nom.
 *
 * Table de suivi : `migrations`
 *   id          — PK
 *   version     — nom de la classe (unique)
 *   batch       — numéro du lot d'exécution (pour rollback)
 *   executed_at — horodatage
 */
class MigrationRunner
{
    private const TABLE = 'migrations';

    public function __construct(
        private readonly Connection $db,
        private readonly string $migrationsPath,
        private readonly string $migrationsNamespace = 'Migrations',
    ) {
        $this->ensureMigrationsTable();
    }

    // ------------------------------------------------------------------
    // Commandes publiques
    // ------------------------------------------------------------------

    /**
     * Exécute toutes les migrations en attente.
     *
     * @return string[] Liste des versions exécutées.
     */
    public function migrate(): array
    {
        $pending = $this->getPending();

        if (empty($pending)) {
            return [];
        }

        $batch = $this->getNextBatch();
        $ran   = [];

        foreach ($pending as $version => $migration) {
            $this->runUp($migration, $version, $batch);
            $ran[] = $version;
        }

        return $ran;
    }

    /**
     * Annule le dernier lot de migrations (batch).
     *
     * @return string[] Liste des versions annulées.
     */
    public function rollback(): array
    {
        $lastBatch = $this->getLastBatch();

        if ($lastBatch === 0) {
            return [];
        }

        $rows    = $this->db->fetchAll(
            'SELECT version FROM ' . self::TABLE . ' WHERE batch = ? ORDER BY id DESC',
            [$lastBatch],
        );
        $rolledBack = [];

        foreach ($rows as $row) {
            $version   = $row['version'];
            $migration = $this->loadMigration($version);

            if ($migration !== null) {
                $this->runDown($migration, $version);
                $rolledBack[] = $version;
            }
        }

        return $rolledBack;
    }

    /**
     * Retourne le statut de toutes les migrations connues.
     *
     * @return array<int, array{version: string, status: string, batch: int|null, executed_at: string|null, description: string}>
     */
    public function status(): array
    {
        $all    = $this->getAllMigrations();
        $ran    = $this->getRan();
        $result = [];

        foreach ($all as $version => $migration) {
            $info = $ran[$version] ?? null;

            $result[] = [
                'version'     => $version,
                'description' => $migration->getDescription(),
                'status'      => $info ? 'Exécutée' : 'En attente',
                'batch'       => $info ? (int) $info['batch'] : null,
                'executed_at' => $info['executed_at'] ?? null,
            ];
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Exécution
    // ------------------------------------------------------------------

    private function runUp(AbstractMigration $migration, string $version, int $batch): void
    {
        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();

        try {
            $migration->setConnection($this->db);
            $migration->up();

            $this->db->query(
                'INSERT INTO ' . self::TABLE . ' (version, batch, executed_at) VALUES (?, ?, ?)',
                [$version, $batch, date('Y-m-d H:i:s')],
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw new \RuntimeException("Échec de la migration [$version] : " . $e->getMessage(), 0, $e);
        }
    }

    private function runDown(AbstractMigration $migration, string $version): void
    {
        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();

        try {
            $migration->setConnection($this->db);
            $migration->down();

            $this->db->query(
                'DELETE FROM ' . self::TABLE . ' WHERE version = ?',
                [$version],
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw new \RuntimeException("Échec du rollback [$version] : " . $e->getMessage(), 0, $e);
        }
    }

    // ------------------------------------------------------------------
    // Chargement des fichiers
    // ------------------------------------------------------------------

    /**
     * @return array<string, AbstractMigration>
     */
    private function getAllMigrations(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files      = glob($this->migrationsPath . '/Version*.php');
        $migrations = [];

        sort($files); // tri alphabétique = chronologique

        foreach ($files as $file) {
            $version   = basename($file, '.php');
            $migration = $this->loadMigration($version);

            if ($migration !== null) {
                $migrations[$version] = $migration;
            }
        }

        return $migrations;
    }

    private function loadMigration(string $version): ?AbstractMigration
    {
        $file = $this->migrationsPath . '/' . $version . '.php';

        if (!file_exists($file)) {
            return null;
        }

        require_once $file;

        $class = $this->migrationsNamespace . '\\' . $version;

        if (!class_exists($class)) {
            return null;
        }

        return new $class();
    }

    // ------------------------------------------------------------------
    // Requêtes sur la table migrations
    // ------------------------------------------------------------------

    /**
     * @return array<string, AbstractMigration>
     */
    private function getPending(): array
    {
        $all = $this->getAllMigrations();
        $ran = $this->getRan();

        return array_filter(
            $all,
            fn (AbstractMigration $m, string $version) => !isset($ran[$version]),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @return array<string, array{batch: string, executed_at: string}>
     */
    private function getRan(): array
    {
        $rows = $this->db->fetchAll('SELECT version, batch, executed_at FROM ' . self::TABLE);
        $map  = [];

        foreach ($rows as $row) {
            $map[$row['version']] = $row;
        }

        return $map;
    }

    private function getNextBatch(): int
    {
        return $this->getLastBatch() + 1;
    }

    private function getLastBatch(): int
    {
        $row = $this->db->fetchOne('SELECT MAX(batch) as max_batch FROM ' . self::TABLE);

        return (int) ($row['max_batch'] ?? 0);
    }

    private function ensureMigrationsTable(): void
    {
        $this->db->query('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            version     VARCHAR(255) NOT NULL UNIQUE,
            batch       INTEGER      NOT NULL,
            executed_at DATETIME     NOT NULL
        )');
    }
}
