<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CommentRepository;
use Framework\ORM\Attribute\Column;
use Framework\ORM\Attribute\Entity;
use Framework\ORM\Attribute\GeneratedValue;
use Framework\ORM\Attribute\Id;
use Framework\ORM\Attribute\ManyToOne;

#[Entity(table: 'comments', repositoryClass: CommentRepository::class)]
class Comment
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private ?int $id = null;

    #[Column(type: 'string')]
    private string $body;

    // ── ManyToOne → Post ──────────────────────────────────────────────
    #[Column(name: 'post_id', type: 'integer')]
    private int $postId;

    #[ManyToOne(targetEntity: Post::class, joinColumn: 'post_id')]
    private ?Post $post = null;

    // ── ManyToOne → User ──────────────────────────────────────────────
    #[Column(name: 'user_id', type: 'integer', nullable: true)]
    private ?int $userId = null;

    #[ManyToOne(targetEntity: User::class, joinColumn: 'user_id')]
    private ?User $author = null;

    #[Column(name: 'created_at', type: 'string', nullable: true)]
    private ?string $createdAt = null;

    public function __construct(string $body, int $postId, ?int $userId = null)
    {
        $this->body      = $body;
        $this->postId    = $postId;
        $this->userId    = $userId;
        $this->createdAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    public function getId(): ?int         { return $this->id; }
    public function getBody(): string     { return $this->body; }
    public function getPostId(): int      { return $this->postId; }
    public function getPost(): ?Post      { return $this->post; }
    public function getUserId(): ?int     { return $this->userId; }
    public function getAuthor(): ?User    { return $this->author; }
    public function getCreatedAt(): ?string { return $this->createdAt; }

    public function setBody(string $body): static { $this->body = $body; return $this; }
}
