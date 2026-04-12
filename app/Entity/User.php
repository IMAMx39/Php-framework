<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Framework\ORM\Attribute\Column;
use Framework\ORM\Attribute\Entity;
use Framework\ORM\Attribute\GeneratedValue;
use Framework\ORM\Attribute\Id;

#[Entity(table: 'users', repositoryClass: UserRepository::class)]
class User
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private ?int $id = null;

    #[Column(type: 'string', length: 100)]
    private string $name;

    #[Column(type: 'string', length: 180, unique: true)]
    private string $email;

    #[Column(name: 'is_active', type: 'boolean')]
    private bool $isActive = true;

    #[Column(name: 'created_at', type: 'string', nullable: true)]
    private ?string $createdAt = null;

    public function __construct(string $name, string $email)
    {
        $this->name      = $name;
        $this->email     = $email;
        $this->createdAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    // ------------------------------------------------------------------
    // Getters
    // ------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    // ------------------------------------------------------------------
    // Setters
    // ------------------------------------------------------------------

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function setActive(bool $active): static
    {
        $this->isActive = $active;

        return $this;
    }
}
