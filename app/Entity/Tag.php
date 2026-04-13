<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TagRepository;
use Framework\ORM\Attribute\Column;
use Framework\ORM\Attribute\Entity;
use Framework\ORM\Attribute\GeneratedValue;
use Framework\ORM\Attribute\Id;
use Framework\ORM\Attribute\ManyToMany;

#[Entity(table: 'tags', repositoryClass: TagRepository::class)]
class Tag
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private ?int $id = null;

    #[Column(type: 'string', length: 50, unique: true)]
    private string $name;

    #[Column(type: 'string', length: 7, nullable: true)]
    private ?string $color = null;

    // ── ManyToMany → Post (côté inverse) ─────────────────────────────
    #[ManyToMany(
        targetEntity: Post::class,
        joinTable: 'post_tags',
        joinColumn: 'tag_id',
        inverseJoinColumn: 'post_id',
    )]
    private array $posts = [];

    public function __construct(string $name, ?string $color = null)
    {
        $this->name  = $name;
        $this->color = $color;
    }

    public function getId(): ?int      { return $this->id; }
    public function getName(): string  { return $this->name; }
    public function getColor(): ?string { return $this->color; }

    /** @return Post[] */
    public function getPosts(): array  { return $this->posts; }

    public function setName(string $name): static   { $this->name  = $name;  return $this; }
    public function setColor(?string $c): static    { $this->color = $c;     return $this; }
}
