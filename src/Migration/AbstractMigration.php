<?php

declare(strict_types=1);

namespace Framework\Migration;

use Framework\Database\Connection;

/**
 * Classe de base pour toutes les migrations.
 *
 * Exemple d'une migration :
 *
 *   class Version20260412000001CreateUsersTable extends AbstractMigration
 *   {
 *       public function up(): void
 *       {
 *           $this->execute('CREATE TABLE users (
 *               id         INTEGER PRIMARY KEY AUTOINCREMENT,
 *               name       VARCHAR(100) NOT NULL,
 *               email      VARCHAR(180) NOT NULL UNIQUE,
 *               is_active  TINYINT(1)   NOT NULL DEFAULT 1,
 *               created_at DATETIME
 *           )');
 *       }
 *
 *       public function down(): void
 *       {
 *           $this->execute('DROP TABLE IF EXISTS users');
 *       }
 *   }
 */
abstract class AbstractMigration
{
    private Connection $connection;

    /** @internal Injecté par le MigrationRunner. */
    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }

    /** Applique la migration (montée de version). */
    abstract public function up(): void;

    /** Annule la migration (retour arrière). */
    abstract public function down(): void;

    /**
     * Description lisible de la migration (optionnel).
     * Affiché dans migrate:status.
     */
    public function getDescription(): string
    {
        return '';
    }

    // ------------------------------------------------------------------
    // Helpers disponibles dans up() / down()
    // ------------------------------------------------------------------

    /**
     * Exécute une requête SQL brute.
     */
    protected function execute(string $sql): void
    {
        $this->connection->query($sql);
    }

    /**
     * Accès direct au PDO pour les opérations avancées (transactions, etc.).
     */
    protected function getConnection(): Connection
    {
        return $this->connection;
    }
}
