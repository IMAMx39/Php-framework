<?php

declare(strict_types=1);

namespace App\Controller;

use Framework\Controller\AbstractController;
use Framework\Http\JsonResponse;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'routes' => [
                ['method' => 'GET',  'path' => '/',           'description' => 'Page d\'accueil'],
                ['method' => 'GET',  'path' => '/hello/{name}','description' => 'Salutation personnalisée'],
                ['method' => 'GET',  'path' => '/api/status', 'description' => 'Statut JSON de l\'API'],
                ['method' => 'POST', 'path' => '/api/echo',   'description' => 'Echo du body JSON'],
            ],
        ]);
    }

    #[Route('/hello/{name}', name: 'hello')]
    public function hello(string $name): Response
    {
        return $this->render('home/hello.html.twig', [
            'name' => $name,
        ]);
    }

    #[Route('/api/status', name: 'api.status')]
    public function status(Request $request): JsonResponse
    {
        return $this->json([
            'status'  => 'ok',
            'method'  => $request->getMethod(),
            'path'    => $request->getUri(),
            'version' => '1.0.0',
        ]);
    }

    #[Route('/api/echo', name: 'api.echo', methods: ['POST'])]
    public function echo(Request $request): JsonResponse
    {
        $data = $request->isJson() ? $request->json() : $request->all();

        return $this->json(['received' => $data]);
    }
}
