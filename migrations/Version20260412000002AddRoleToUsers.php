<?php

declare(strict_types=1);

namespace Migrations;

use Framework\Migration\AbstractMigration;

class Version20260412000002AddRoleToUsers extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la colonne role dans users';
    }

    public function up(): void
    {
        $this->execute("ALTER TABLE users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user'");
    }

    public function down(): void
    {
        // SQLite ne supporte pas DROP COLUMN avant 3.35.0
        // Sur MySQL/PostgreSQL : ALTER TABLE users DROP COLUMN role
        $this->execute('CREATE TABLE users_backup AS SELECT id, name, email, is_active, created_at FROM users');
        $this->execute('DROP TABLE users');
        $this->execute('ALTER TABLE users_backup RENAME TO users');
    }
}
