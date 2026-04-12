<?php

declare(strict_types=1);

namespace App\Controller;

use Framework\Controller\AbstractController;
use Framework\Http\JsonResponse;
use Framework\Http\Request;
use Framework\Http\Response;

class HomeController extends AbstractController
{
    public function index(): Response
    {
        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <title>PHP Framework</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 60px auto; color: #333; }
                h1   { color: #4f46e5; }
                code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
                ul   { line-height: 2; }
            </style>
        </head>
        <body>
            <h1>Bienvenue sur PHP Framework !</h1>
            <p>Ton framework fait maison fonctionne correctement.</p>
            <h2>Routes disponibles</h2>
            <ul>
                <li><code>GET /</code> — Page d'accueil</li>
                <li><code>GET /hello/{name}</code> — Salutation personnalisée</li>
                <li><code>GET /api/status</code> — Statut JSON</li>
                <li><code>POST /api/echo</code> — Echo du body JSON</li>
            </ul>
        </body>
        </html>
        HTML;

        return $this->response($html);
    }

    public function hello(string $name): Response
    {
        return $this->response("<h1>Bonjour, " . htmlspecialchars($name) . " !</h1>");
    }

    public function status(Request $request): JsonResponse
    {
        return $this->json([
            'status'  => 'ok',
            'method'  => $request->getMethod(),
            'path'    => $request->getUri(),
            'version' => '1.0.0',
        ]);
    }

    public function echo(Request $request): JsonResponse
    {
        $data = $request->isJson() ? $request->json() : $request->all();

        return $this->json([
            'received' => $data,
        ]);
    }
}
