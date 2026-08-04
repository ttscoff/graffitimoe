<?php

declare(strict_types=1);

namespace Graffiti\Http;

final class Request
{
    /** @var array<string, mixed> */
    public array $query;

    /** @var array<string, string> */
    public array $headers;

    /** @var array<string, mixed> */
    public array $post;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     */
    public function __construct(
        public string $method,
        string $path,
        array $query,
        array $server,
        public string $rawBody,
        array $post,
        public string $ip,
    ) {
        $this->path = self::normalizePath($path);
        $this->query = $query;
        $this->headers = self::headersFromServer($server);
        $this->post = $post;
    }

    public string $path;

    public static function fromGlobals(): self
    {
        $server = $_SERVER;

        return new self(
            (string) ($server['REQUEST_METHOD'] ?? 'GET'),
            (string) ($server['REQUEST_URI'] ?? '/'),
            $_GET,
            $server,
            (string) file_get_contents('php://input'),
            $_POST,
            (string) ($server['REMOTE_ADDR'] ?? ''),
        );
    }

    public function isBrowser(): bool
    {
        return preg_match('/Mozilla|Chrome|Safari|Firefox|Edg/i', $this->userAgent()) === 1
            || str_contains(strtolower($this->accept()), 'text/html');
    }

    public function wantsPlainText(): bool
    {
        if (!$this->isBrowser()) {
            return true;
        }

        if (preg_match('/curl|Wget/i', $this->userAgent()) === 1) {
            return true;
        }

        $accept = trim($this->accept());
        if ($accept === '' || preg_match('/^\*\/\*(?:\s*;.*)?$/i', $accept) === 1) {
            return true;
        }

        return $this->mediaTypeQuality('text/plain') > $this->mediaTypeQuality('text/html');
    }

    public function colorEnabled(): bool
    {
        return ($this->query['color'] ?? null) === 'always';
    }

    private static function normalizePath(string $path): string
    {
        $parsedPath = parse_url($path, PHP_URL_PATH);

        if (!is_string($parsedPath) || $parsedPath === '') {
            return '/';
        }

        if ($parsedPath !== '/') {
            $parsedPath = rtrim($parsedPath, '/');
        }

        return $parsedPath;
    }

    /** @param array<string, mixed> $server
     *  @return array<string, string>
     */
    private static function headersFromServer(array $server): array
    {
        $headers = [];

        foreach ($server as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }

            if (str_starts_with($name, 'HTTP_')) {
                $name = substr($name, 5);
            } elseif (!in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                continue;
            }

            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))))] = $value;
        }

        return $headers;
    }

    private function userAgent(): string
    {
        return $this->headers['User-Agent'] ?? '';
    }

    private function accept(): string
    {
        return $this->headers['Accept'] ?? '';
    }

    private function mediaTypeQuality(string $wanted): float
    {
        $quality = -1.0;

        foreach (explode(',', $this->accept()) as $entry) {
            $parts = array_map('trim', explode(';', $entry));
            if (strtolower($parts[0]) !== $wanted) {
                continue;
            }

            $entryQuality = 1.0;
            foreach (array_slice($parts, 1) as $parameter) {
                if (str_starts_with($parameter, 'q=')) {
                    $entryQuality = (float) substr($parameter, 2);
                }
            }
            $quality = max($quality, $entryQuality);
        }

        return $quality;
    }
}
