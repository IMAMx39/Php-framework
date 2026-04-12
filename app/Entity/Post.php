<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PostRepository;
use Framework\ORM\Attribute\Column;
use Framework\ORM\Attribute\Entity;
use Framework\ORM\Attribute\GeneratedValue;
use Framework\ORM\Attribute\Id;
use Framework\ORM\Attribute\ManyToOne;
use Framework\ORM\Attribute\OneToMany;

#[Entity(table: 'posts', repositoryClass: PostRepository::class)]
class Post
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private ?int $id = null;

    #[Column(type: 'string', length: 255)]
    private string $title;

    #[Column(type: 'string', nullable: true)]
    private ?string $content = null;

    // ── ManyToOne → User ──────────────────────────────────────────────
    // FK stockée en base
    #[Column(name: 'user_id', type: 'integer', nullable: true)]
    private ?int $userId = null;

    // Objet lié — chargé via find($id, relations: ['author'])
    #[ManyToOne(targetEntity: User::class, joinColumn: 'user_id')]
    private ?User $author = null;

    // ── OneToMany → Comment ───────────────────────────────────────────
    // Chargé via find($id, relations: ['comments'])
    #[OneToMany(targetEntity: Comment::class, mappedBy: 'post_id', orderBy: ['created_at' => 'ASC'])]
    private array $comments = [];

    #[Column(name: 'created_at', type: 'string', nullable: true)]
    private ?string $createdAt = null;

    public function __construct(string $title, ?string $content = null, ?int $userId = null)
    {
        $this->title     = $title;
        $this->content   = $content;
        $this->userId    = $userId;
        $this->createdAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    public function getId(): ?int           { return $this->id; }
    public function getTitle(): string      { return $this->title; }
    public function getContent(): ?string   { return $this->content; }
    public function getUserId(): ?int       { return $this->userId; }
    public function getAuthor(): ?User      { return $this->author; }
    public function getComments(): array    { return $this->comments; }
    public function getCreatedAt(): ?string { return $this->createdAt; }

    public function setTitle(string $title): static      { $this->title   = $title;   return $this; }
    public function setContent(?string $c): static       { $this->content = $c;       return $this; }
    public function setUserId(?int $userId): static      { $this->userId  = $userId;  return $this; }
}
