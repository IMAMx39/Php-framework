<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Post;
use App\Entity\Tag;
use Framework\ORM\AbstractRepository;

class PostRepository extends AbstractRepository
{
    protected function getEntityClass(): string
    {
        return Post::class;
    }

    // ------------------------------------------------------------------
    // Lecture avec relations
    // ------------------------------------------------------------------

    /** @return Post[] */
    public function findByUser(int $userId, bool $withComments = false): array
    {
        return $this->findBy(
            criteria:  ['user_id' => $userId],
            orderBy:   ['created_at' => 'DESC'],
            relations: $withComments ? ['comments'] : [],
        );
    }

    public function findWithAll(int $id): ?Post
    {
        /** @var Post|null */
        return $this->find($id, relations: ['author', 'comments', 'tags']);
    }

    // ------------------------------------------------------------------
    // Gestion des tags (ManyToMany)
    // ------------------------------------------------------------------

    public function addTag(Post $post, Tag $tag): void
    {
        $this->attach($post, $tag, 'tags');
    }

    public function removeTag(Post $post, Tag $tag): void
    {
        $this->detach($post, $tag, 'tags');
    }

    /**
     * Remplace tous les tags du post par la liste donnée.
     *
     * @param Tag[] $tags
     */
    public function syncTags(Post $post, array $tags): void
    {
        $this->sync($post, $tags, 'tags');
    }
}
