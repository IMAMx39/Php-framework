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
            'framework' => 'PHP Framework',
        ]);
    }

    #[Route('/api/status', name: 'api.status')]
    public function status(Request $request): JsonResponse
    {
        return $this->json([
            'status'  => 'ok',
            'version' => '1.0.0',
        ]);
    }
}
