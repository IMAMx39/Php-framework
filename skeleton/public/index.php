<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Framework\Core\Application;

$app = new Application(dirname(__DIR__));
$app->run();
