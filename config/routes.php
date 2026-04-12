<?php

declare(strict_types=1);

use Framework\Routing\Router;

/**
 * Routes déclarées manuellement (optionnel).
 *
 * Les routes des controllers sont automatiquement découvertes
 * via l'attribut PHP 8 #[Route(...)] — pas besoin de les répéter ici.
 *
 * Utilise ce fichier uniquement pour des routes sans controller
 * (closures, redirections rapides, etc.).
 *
 * Exemple :
 *
 *   $router->get('/ping', fn() => 'pong', 'ping');
 */
return function (Router $router): void {
    // Tes routes manuelles ici...
};
