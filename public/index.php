<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/views.php';

try {
    /** @var array{
     *     db_path: string,
     *     admin_password: string,
     *     ip_hash_secret: string,
     *     rate_limit_max: int,
     *     rate_limit_window_seconds: int,
     *     base_url: string,
     *     session_name: string
     * } $config
     */
    $configPath = getenv('GRAFFITI_CONFIG');
    if ($configPath === false || $configPath === '') {
        $configPath = dirname(__DIR__) . '/config/config.php';
    }
    $config = require $configPath;

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['REQUEST_SCHEME'] ?? '') === 'https'
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $isHttps,
    ]);

    session_name($config['session_name']);
    session_start();

    $request = \Graffiti\Http\Request::fromGlobals();
    $repo = new \Graffiti\MessageRepository(\Graffiti\Database::connect($config['db_path']));

    if ($request->method === 'GET' && $request->path === '/') {
        $response = $request->isBrowser()
            ? \Graffiti\Http\Response::redirect('/add')
            : (new \Graffiti\Handlers\RandomHandler($repo, $config['base_url']))->handle($request);
    } elseif ($request->method === 'GET' && $request->path === '/random') {
        $response = (new \Graffiti\Handlers\RandomHandler($repo, $config['base_url']))->handle($request);
    } elseif (in_array($request->method, ['GET', 'POST'], true) && $request->path === '/add') {
        $response = (new \Graffiti\Handlers\AddHandler(
            $repo,
            new \Graffiti\RateLimiter(
                $repo,
                $config['rate_limit_max'],
                $config['rate_limit_window_seconds'],
            ),
            $config['ip_hash_secret'],
            'render_add',
        ))->handle($request);
    } elseif (in_array($request->method, ['GET', 'POST'], true) && $request->path === '/admin') {
        $response = (new \Graffiti\Handlers\AdminHandler(
            $repo,
            $config['admin_password'],
            new \Graffiti\PhpSession(),
            'render_admin',
            'render_admin_login',
        ))->handle($request);
    } else {
        $response = $request->isBrowser()
            ? \Graffiti\Http\Response::html('<h1>Not found.</h1>', 404)
            : \Graffiti\Http\Response::plain('Not found.', 404);
    }
} catch (\Throwable) {
    $response = \Graffiti\Http\Response::plain('Something went wrong.', 500);
}

$response->emit();
