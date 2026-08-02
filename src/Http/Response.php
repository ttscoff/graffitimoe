<?php

declare(strict_types=1);

namespace Graffiti\Http;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {
    }

    public static function plain(string $body, int $status = 200): self
    {
        if ($body !== '' && !str_ends_with($body, "\n")) {
            $body .= "\n";
        }

        return new self($status, ['Content-Type' => 'text/plain; charset=utf-8'], $body);
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=utf-8'], $body);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, ['Location' => $location], '');
    }

    public function emit(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
