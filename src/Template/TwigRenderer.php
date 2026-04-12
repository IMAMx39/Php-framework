<?php

declare(strict_types=1);

namespace Framework\Template;

use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

/**
 * Wrapper autour de Twig\Environment.
 *
 * Configuré automatiquement depuis les variables d'environnement :
 *   APP_DEBUG=true  → cache désactivé + extension debug ({{ dump() }})
 *   APP_DEBUG=false → templates compilés dans var/cache/twig/
 *
 * Utilisation dans un controller :
 *   return $this->render('home/index.html.twig', ['user' => $user]);
 *
 * Chemins de templates multiples :
 *   $renderer->addPath('/path/to/templates', 'admin');
 *   → @admin/layout.html.twig
 */
class TwigRenderer
{
    private Environment $twig;

    public function __construct(
        string $templatesPath,
        string $cachePath,
        bool   $debug = false,
    ) {
        $loader = new FilesystemLoader($templatesPath);

        $this->twig = new Environment($loader, [
            'cache'            => $debug ? false : $cachePath,
            'debug'            => $debug,
            'auto_reload'      => $debug,
            'strict_variables' => true,
        ]);

        if ($debug) {
            $this->twig->addExtension(new DebugExtension());
        }
    }

    // ------------------------------------------------------------------
    // Rendu
    // ------------------------------------------------------------------

    /**
     * Rend un template et retourne le HTML produit.
     *
     * @param array<string, mixed> $context Variables passées au template.
     */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }

    // ------------------------------------------------------------------
    // Configuration avancée
    // ------------------------------------------------------------------

    /**
     * Ajoute un répertoire de templates supplémentaire.
     *
     * @param string $namespace Namespace Twig (ex: "admin" → @admin/layout.html.twig)
     */
    public function addPath(string $path, string $namespace = FilesystemLoader::MAIN_NAMESPACE): void
    {
        /** @var FilesystemLoader $loader */
        $loader = $this->twig->getLoader();
        $loader->addPath($path, $namespace);
    }

    /**
     * Ajoute une variable globale accessible dans tous les templates.
     */
    public function addGlobal(string $name, mixed $value): void
    {
        $this->twig->addGlobal($name, $value);
    }

    /**
     * Accès direct à l'environnement Twig pour les extensions, filtres, etc.
     */
    public function getEnvironment(): Environment
    {
        return $this->twig;
    }
}
