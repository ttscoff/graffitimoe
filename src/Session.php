<?php

declare(strict_types=1);

namespace Graffiti;

interface SessionBag
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function isAdmin(): bool;
}

final class ArraySession implements SessionBag
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function isAdmin(): bool
    {
        return $this->get('admin') === 1;
    }
}

final class PhpSession implements SessionBag
{
    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function isAdmin(): bool
    {
        return $this->get('admin') === 1;
    }
}
