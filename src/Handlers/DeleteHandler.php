<?php

declare(strict_types=1);

namespace Graffiti\Handlers;

use Graffiti\Http\Request;
use Graffiti\Http\Response;
use Graffiti\MessageRepository;
use Graffiti\OwnedMessages;
use Graffiti\SessionBag;

/** POST /delete — session owner (or unused for admin; admins use /admin). */
final class DeleteHandler
{
    public function __construct(
        private MessageRepository $repo,
        private SessionBag $session,
        private OwnedMessages $owned,
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
        if ($id <= 0 || !$this->owned->owns($id)) {
            return $this->reject($request, 403, 'Forbidden.');
        }

        $this->repo->delete($id);
        $this->owned->forget($id);

        return $request->wantsPlainText()
            ? Response::plain('Deleted.', 200)
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
