<?php

declare(strict_types=1);

namespace Graffiti;

final class RateLimiter
{
    public function __construct(
        private MessageRepository $repo,
        private int $max,
        private int $windowSeconds,
    ) {
    }

    public static function hashIp(string $ip, string $secret): string
    {
        return hash_hmac('sha256', $ip, $secret);
    }

    public function allowSubmit(string $ipHash): bool
    {
        return $this->repo->countRecentByIpHash($ipHash, $this->windowSeconds) < $this->max;
    }
}
