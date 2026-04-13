<?php

declare(strict_types=1);

namespace Migrations;

use Framework\Migration\AbstractMigration;

class Version20260412000003CreateTagsAndPostTags extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création des tables tags et post_tags (pivot ManyToMany)';
    }

    public function up(): void
    {
        $this->execute('CREATE TABLE IF NOT EXISTS tags (
            id    INTEGER      PRIMARY KEY AUTOINCREMENT,
            name  VARCHAR(50)  NOT NULL UNIQUE,
            color VARCHAR(7)
        )');

        // Table pivot — pas d'entité PHP, gérée via attach/detach/sync
        $this->execute('CREATE TABLE IF NOT EXISTS post_tags (
            post_id INTEGER NOT NULL,
            tag_id  INTEGER NOT NULL,
            PRIMARY KEY (post_id, tag_id),
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (tag_id)  REFERENCES tags(id)  ON DELETE CASCADE
        )');
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS post_tags');
        $this->execute('DROP TABLE IF EXISTS tags');
    }
}
