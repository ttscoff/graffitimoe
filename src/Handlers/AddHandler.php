<?php

declare(strict_types=1);

namespace Graffiti\Handlers;

use Graffiti\Http\Request;
use Graffiti\Http\Response;
use Graffiti\MessageRepository;
use Graffiti\MessageSanitizer;
use Graffiti\MessageQuality;
use Graffiti\OwnedMessages;
use Graffiti\RateLimiter;
use Graffiti\SessionBag;
use InvalidArgumentException;

final class AddHandler
{
    public const PUBLIC_RECENT_LIMIT = 10;
    public const ADMIN_RECENT_LIMIT = 50;

    /** @param callable(array<string, mixed>): string $renderAddPage */
    public function __construct(
        private MessageRepository $repo,
        private RateLimiter $limiter,
        private string $ipSecret,
        private SessionBag $session,
        private OwnedMessages $owned,
        private $renderAddPage,
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($request->method === 'GET') {
            $isAdmin = $this->session->isAdmin();
            $ownedIds = $this->owned->idList();
            return Response::html(($this->renderAddPage)([
                'recent' => $this->repo->recent(
                    $isAdmin ? self::ADMIN_RECENT_LIMIT : self::PUBLIC_RECENT_LIMIT
                ),
                'ok' => ($request->query['ok'] ?? null) === '1',
                'error' => $request->query['error'] ?? null,
                'colors' => MessageSanitizer::COLORS,
                'isAdmin' => $isAdmin,
                'ownedIds' => $ownedIds,
                'csrfToken' => ($isAdmin || $ownedIds !== []) ? $this->session->csrfToken() : '',
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

        $spans = MessageSanitizer::normalizeSpans($body, $this->valueFrom($request, 'spans'));
        $color = MessageSanitizer::normalizeColor($this->valueFrom($request, 'color'));
        $bold = MessageSanitizer::normalizeBold($this->valueFrom($request, 'bold'));
        if ($spans !== null) {
            $color = $spans[0]['c'];
            $bold = !empty($spans[0]['b']);
        }

        $id = $this->repo->create(
            $body,
            $color,
            $bold,
            $ipHash,
            $spans,
            MessageQuality::shouldFlag($body),
        );
        $this->owned->remember($id);

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
