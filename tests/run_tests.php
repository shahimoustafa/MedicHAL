<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$phpunitBin = __DIR__ . '/../vendor/bin/phpunit';
if (file_exists($phpunitBin)) {
    passthru(PHP_BINARY . ' ' . escapeshellarg($phpunitBin), $exitCode);
    exit($exitCode);
}
