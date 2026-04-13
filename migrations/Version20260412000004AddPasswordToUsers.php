<?php

declare(strict_types=1);

namespace Migrations;

use Framework\Migration\AbstractMigration;

class Version20260412000004AddPasswordToUsers extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la colonne password (hash bcrypt) dans users';
    }

    public function up(): void
    {
        $this->execute('ALTER TABLE users ADD COLUMN password VARCHAR(255)');
    }

    public function down(): void
    {
        // SQLite < 3.35 ne supporte pas DROP COLUMN
        // MySQL / PostgreSQL :
        // $this->execute('ALTER TABLE users DROP COLUMN password');

        $this->execute('CREATE TABLE users_backup AS
            SELECT id, name, email, is_active, role, created_at FROM users');
        $this->execute('DROP TABLE users');
        $this->execute('ALTER TABLE users_backup RENAME TO users');
    }
}
