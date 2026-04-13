<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use Framework\ORM\AbstractRepository;

class TagRepository extends AbstractRepository
{
    protected function getEntityClass(): string
    {
        return Tag::class;
    }

    public function findByName(string $name): ?Tag
    {
        /** @var Tag|null */
        return $this->findOneBy(['name' => $name]);
    }
}
