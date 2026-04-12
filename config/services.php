<?php

declare(strict_types=1);

use Framework\Container\Container;

/**
 * Enregistre ici tes services dans le conteneur.
 *
 * Exemples :
 *
 *   $container->singleton(Database::class, fn() => new Database($_ENV['DB_DSN']));
 *   $container->bind(MailerInterface::class, SmtpMailer::class);
 */
return function (Container $container): void {
    // Tes services ici...
};
