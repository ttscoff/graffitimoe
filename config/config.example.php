<?php

declare(strict_types=1);

return [
    'db_path' => __DIR__ . '/../data/graffiti.sqlite',
    'admin_password' => 'change-me',
    'ip_hash_secret' => 'change-me-long-random',
    'rate_limit_max' => 5,
    'rate_limit_window_seconds' => 600,
    'base_url' => 'https://graffiti.moe',
    'session_name' => 'graffiti_admin',
    // Optional. When unset, Secure cookies follow X-Forwarded-Proto / HTTPS.
    // Set false for local HTTP reverse proxies that terminate TLS at the backend.
    // 'session_cookie_secure' => false,
];
