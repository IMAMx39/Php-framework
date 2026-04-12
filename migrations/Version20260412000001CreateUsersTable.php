<?php

declare(strict_types=1);

namespace Migrations;

use Framework\Migration\AbstractMigration;

class Version20260412000001CreateUsersTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table users';
    }

    public function up(): void
    {
        $this->execute('CREATE TABLE IF NOT EXISTS users (
            id         INTEGER      PRIMARY KEY AUTOINCREMENT,
            name       VARCHAR(100) NOT NULL,
            email      VARCHAR(180) NOT NULL UNIQUE,
            is_active  TINYINT(1)   NOT NULL DEFAULT 1,
            created_at DATETIME
        )');
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS users');
    }
}
