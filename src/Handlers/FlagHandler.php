<?php

declare(strict_types=1);

namespace Graffiti\Handlers;

use Graffiti\FlaggedMessages;
use Graffiti\Http\Request;
use Graffiti\Http\Response;
use Graffiti\MessageRepository;
use Graffiti\RateLimiter;
use Graffiti\SessionBag;

/** POST /flag — toggles a community flag on a message by IP. */
final class FlagHandler
{
    public function __construct(
        private MessageRepository $repo,
        private SessionBag $session,
        private FlaggedMessages $flagged,
        private string $ipSecret,
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($request->method !== 'POST') {
            return Response::plain('Method not allowed.', 405);
        }

        if (!$this->hasValidCsrfToken($request)) {
            return $this->reject($request, 403, 'Forbidden.');
        }

        $id = (int) ($request->post['id'] ?? 0);
        if ($id <= 0) {
            return $this->reject($request, 400, 'Bad id.');
        }

        $ipHash = RateLimiter::hashIp($request->ip, $this->ipSecret);
        $result = $this->repo->toggleCommunityFlag($id, $ipHash);
        if ($result === null) {
            return $this->reject($request, 404, 'Not found.');
        }

        if ($result === 'flagged') {
            $this->flagged->remember($id);
            $msg = 'Flagged.';
        } else {
            $this->flagged->forget($id);
            $msg = 'Unflagged.';
        }

        return $request->wantsPlainText()
            ? Response::plain($msg, 200)
            : Response::redirect($this->safeNext($request));
    }

    private function hasValidCsrfToken(Request $request): bool
    {
        $submitted = (string) ($request->post['csrf_token'] ?? '');

        return $submitted !== '' && hash_equals($this->session->csrfToken(), $submitted);
    }

    private function safeNext(Request $request): string
    {
        $next = (string) ($request->post['next'] ?? '/add');
        if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, '\\')) {
            return '/add';
        }

        return $next;
    }

    private function reject(Request $request, int $status, string $message): Response
    {
        return $request->wantsPlainText()
            ? Response::plain($message, $status)
            : Response::redirect('/add?error=invalid');
    }
}
