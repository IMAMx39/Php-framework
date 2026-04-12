<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Post;
use Framework\ORM\AbstractRepository;

class PostRepository extends AbstractRepository
{
    protected function getEntityClass(): string
    {
        return Post::class;
    }

    /**
     * Trouve les posts d'un utilisateur avec ses commentaires chargés.
     *
     * @return Post[]
     */
    public function findByUser(int $userId, bool $withComments = false): array
    {
        $relations = $withComments ? ['comments'] : [];

        return $this->findBy(
            criteria:  ['user_id' => $userId],
            orderBy:   ['created_at' => 'DESC'],
            relations: $relations,
        );
    }

    /**
     * Trouve un post avec son auteur et ses commentaires.
     */
    public function findWithAll(int $id): ?Post
    {
        /** @var Post|null */
        return $this->find($id, relations: ['author', 'comments']);
    }
}
