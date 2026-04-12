<?php

declare(strict_types=1);

namespace Framework\ORM\Attribute;

/**
 * Indique que la valeur de la clé primaire est générée automatiquement
 * par la base de données (AUTO_INCREMENT / SERIAL).
 *
 * L'EntityMapper exclura cette colonne des requêtes INSERT.
 * L'id retourné par lastInsertId() sera réinjecté dans l'entité après save().
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class GeneratedValue {}
