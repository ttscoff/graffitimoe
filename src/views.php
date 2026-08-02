<?php

declare(strict_types=1);

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/**
 * Render the /add page.
 *
 * @param array<string, mixed> $vars
 */
function render_add(array $vars): string
{
    ob_start();
    extract($vars);
    require __DIR__ . '/views/add.php';
    return (string) ob_get_clean();
}

/**
 * Render the admin page.
 *
 * @param array<string, mixed> $vars
 */
function render_admin(array $vars): string
{
    ob_start();
    extract($vars);
    require __DIR__ . '/views/admin.php';
    return (string) ob_get_clean();
}

/**
 * Render the admin login page.
 *
 * @param array<string, mixed> $vars
 */
function render_admin_login(array $vars): string
{
    ob_start();
    extract($vars);
    require __DIR__ . '/views/admin_login.php';
    return (string) ob_get_clean();
}
