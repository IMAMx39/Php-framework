<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Comment;
use Framework\ORM\AbstractRepository;

class CommentRepository extends AbstractRepository
{
    protected function getEntityClass(): string
    {
        return Comment::class;
    }

    /**
     * Trouve les commentaires d'un post avec leurs auteurs.
     *
     * @return Comment[]
     */
    public function findByPost(int $postId, bool $withAuthor = false): array
    {
        $relations = $withAuthor ? ['author'] : [];

        return $this->findBy(
            criteria:  ['post_id' => $postId],
            orderBy:   ['created_at' => 'ASC'],
            relations: $relations,
        );
    }
}
