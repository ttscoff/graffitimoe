<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\Database;
use Graffiti\MessageRepository;
use Graffiti\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    public function test_allows_until_max_then_blocks(): void
    {
        $path = sys_get_temp_dir() . '/graffiti_rl_' . uniqid('', true) . '.sqlite';
        $repo = new MessageRepository(Database::connect($path));
        $limiter = new RateLimiter($repo, 2, 600);
        $hash = RateLimiter::hashIp('1.2.3.4', 'secret');
        $this->assertTrue($limiter->allowSubmit($hash));
        $repo->create('a', 'default', false, $hash);
        $this->assertTrue($limiter->allowSubmit($hash));
        $repo->create('b', 'default', false, $hash);
        $this->assertFalse($limiter->allowSubmit($hash));
        @unlink($path);
    }
}
