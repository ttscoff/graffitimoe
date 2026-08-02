<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo "graffiti.moe scaffold\n";
