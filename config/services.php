<?php

declare(strict_types=1);

use Framework\Container\Container;
use Framework\Database\Connection;

return function (Container $container): void {
    /*
     * Base de données — lue depuis DATABASE_URL dans .env.
     *
     * Une seule instance PDO partagée dans toute l'application (singleton).
     * Injectée automatiquement dans n'importe quel service/controller
     * qui déclare Connection en paramètre de constructeur.
     */
    $container->singleton(Connection::class, fn () => new Connection());
};
