<?php

declare(strict_types=1);

use Framework\Routing\Router;

/**
 * Routes déclarées manuellement (optionnel).
 *
 * Les routes sont automatiquement découvertes via #[Route(...)] sur les
 * contrôleurs. Utilise ce fichier pour des routes sans contrôleur.
 *
 * Exemple :
 *   $router->get('/ping', fn() => 'pong', 'ping');
 */
return function (Router $router): void {
    // Tes routes ici...
};
