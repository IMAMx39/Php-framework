<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Framework\ORM\AbstractRepository;

class UserRepository extends AbstractRepository
{
    protected function getEntityClass(): string
    {
        return User::class;
    }

    // ------------------------------------------------------------------
    // Méthodes métier personnalisées
    // ------------------------------------------------------------------

    /**
     * @return User[]
     */
    public function findActive(): array
    {
        return $this->findBy(['is_active' => 1], ['name' => 'ASC']);
    }

    /**
     * Cherche un utilisateur par son adresse email.
     */
    public function findByEmail(string $email): ?User
    {
        /** @var User|null */
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Recherche par nom (LIKE).
     *
     * @return User[]
     */
    public function search(string $term): array
    {
        $rows = $this->createQueryBuilder()
            ->where('name', 'LIKE', "%$term%")
            ->orderBy('name')
            ->get();

        return array_map(
            fn (array $row) => $this->hydrate($row),
            $rows,
        );
    }

    // ------------------------------------------------------------------
    // Helper interne
    // ------------------------------------------------------------------

    private function hydrate(array $row): User
    {
        // Délègue à l'EntityMapper via la réflexion
        $mapper = new \Framework\ORM\EntityMapper();

        return $mapper->hydrate(User::class, $row);
    }
}
