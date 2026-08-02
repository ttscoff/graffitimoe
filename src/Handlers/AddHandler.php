<?php

declare(strict_types=1);

namespace Graffiti\Handlers;

use Graffiti\Http\Request;
use Graffiti\Http\Response;
use Graffiti\MessageRepository;
use Graffiti\MessageSanitizer;
use Graffiti\RateLimiter;
use InvalidArgumentException;

final class AddHandler
{
    /** @param callable(array<string, mixed>): string $renderAddPage */
    public function __construct(
        private MessageRepository $repo,
        private RateLimiter $limiter,
        private string $ipSecret,
        private $renderAddPage,
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($request->method === 'GET') {
            return Response::html(($this->renderAddPage)([
                'recent' => $this->repo->recent(10),
                'ok' => ($request->query['ok'] ?? null) === '1',
                'error' => $request->query['error'] ?? null,
                'colors' => MessageSanitizer::COLORS,
            ]));
        }

        if ($this->hasHoneypotValue($request)) {
            return $this->successResponse($request);
        }

        try {
            $body = MessageSanitizer::sanitizeBody($this->bodyFrom($request));
        } catch (InvalidArgumentException) {
            return $this->errorResponse($request, 400, 'Invalid message.');
        }

        $ipHash = RateLimiter::hashIp($request->ip, $this->ipSecret);
        if (!$this->limiter->allowSubmit($ipHash)) {
            return $this->errorResponse($request, 429, 'Slow down.');
        }

        $this->repo->create(
            $body,
            MessageSanitizer::normalizeColor($this->valueFrom($request, 'color')),
            MessageSanitizer::normalizeBold($this->valueFrom($request, 'bold')),
            $ipHash,
        );

        return $this->successResponse($request);
    }

    private function bodyFrom(Request $request): string
    {
        if (array_key_exists('body', $request->post)) {
            return (string) $request->post['body'];
        }

        return $this->isPlainTextContent($request) ? $request->rawBody : '';
    }

    private function hasHoneypotValue(Request $request): bool
    {
        return trim((string) ($request->post['website'] ?? '')) !== '';
    }

    private function isPlainTextContent(Request $request): bool
    {
        $contentType = strtolower($request->headers['Content-Type'] ?? '');
        return str_starts_with($contentType, 'text/plain');
    }

    private function valueFrom(Request $request, string $key): mixed
    {
        return $request->post[$key] ?? $request->query[$key] ?? null;
    }

    private function successResponse(Request $request): Response
    {
        return $request->wantsPlainText()
            ? Response::plain('Sprayed.', 201)
            : Response::redirect('/add?ok=1');
    }

    private function errorResponse(Request $request, int $status, string $message): Response
    {
        return $request->wantsPlainText()
            ? Response::plain($message, $status)
            : Response::redirect('/add?error=invalid');
    }
}
