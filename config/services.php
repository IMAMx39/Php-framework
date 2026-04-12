<?php

declare(strict_types=1);

use App\Repository\UserRepository;
use Framework\Container\Container;
use Framework\Database\Connection;

return function (Container $container): void {
    /*
     * Base de données — lue depuis DATABASE_URL dans .env.
     * Une seule instance PDO partagée (singleton).
     */
    $container->singleton(Connection::class, fn () => new Connection());

    /*
     * Repositories — injectables via le conteneur ou le constructeur.
     *
     * Exemple dans un controller :
     *   public function __construct(private readonly UserRepository $users) {}
     */
    $container->singleton(UserRepository::class, fn (Container $c) => new UserRepository(
        $c->get(Connection::class),
    ));
};
