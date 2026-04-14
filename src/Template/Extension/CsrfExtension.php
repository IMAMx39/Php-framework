<?php

declare(strict_types=1);

namespace Framework\Template\Extension;

use Framework\Security\CsrfTokenManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Extension Twig qui expose les helpers CSRF dans les templates.
 *
 * Fonctions disponibles :
 *   {{ csrf_field() }}
 *       → <input type="hidden" name="_csrf_token" value="...">
 *
 *   {{ csrf_token() }}
 *       → valeur brute du token (pour les en-têtes AJAX)
 *
 * Enregistrement dans TwigRenderer :
 *   $renderer->addExtension(new CsrfExtension($csrfManager));
 */
class CsrfExtension extends AbstractExtension
{
    public function __construct(private readonly CsrfTokenManager $csrfManager) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'csrf_field',
                $this->renderField(...),
                ['is_safe' => ['html']],
            ),
            new TwigFunction(
                'csrf_token',
                $this->csrfManager->getToken(...),
            ),
        ];
    }

    private function renderField(): string
    {
        $token = htmlspecialchars($this->csrfManager->getToken(), ENT_QUOTES, 'UTF-8');
        $name  = htmlspecialchars(CsrfTokenManager::FIELD_NAME, ENT_QUOTES, 'UTF-8');

        return sprintf('<input type="hidden" name="%s" value="%s">', $name, $token);
    }
}
