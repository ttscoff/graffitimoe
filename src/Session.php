<?php

declare(strict_types=1);

namespace Graffiti;

interface SessionBag
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function isAdmin(): bool;

    /** Returns the per-session CSRF token, generating one on first use. */
    public function csrfToken(): string;

    /** Rotates the session identity (and CSRF token) after a privilege change, e.g. login. */
    public function regenerate(): void;
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

    public function csrfToken(): string
    {
        $token = $this->get('csrf_token');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->set('csrf_token', $token);
        }

        return $token;
    }

    public function regenerate(): void
    {
        unset($this->values['csrf_token']);
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

    public function csrfToken(): string
    {
        $token = $this->get('csrf_token');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->set('csrf_token', $token);
        }

        return $token;
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
        unset($_SESSION['csrf_token']);
    }
}
