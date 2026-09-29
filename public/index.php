<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Bootstrap;

// Gestion des fichiers statiques avec le serveur interne PHP
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($path && is_file(__DIR__ . $path)) {
        return false;
    }
}

// Lancer l'application
Bootstrap::run();
