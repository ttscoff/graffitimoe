<?php

declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($configPath)) {
    $configPath = dirname(__DIR__) . '/config/config.example.php';
}

/** @var array<string, mixed> $config */
$config = require $configPath;

return $config;
