<?php

declare(strict_types=1);

use App\Controller\HomeController;
use Framework\Routing\Router;

return function (Router $router): void {
    $router->get('/', [HomeController::class, 'index'], 'home');
    $router->get('/hello/{name}', [HomeController::class, 'hello'], 'hello');
    $router->get('/api/status', [HomeController::class, 'status'], 'api.status');
    $router->post('/api/echo', [HomeController::class, 'echo'], 'api.echo');
};
