<?php

declare(strict_types=1);

namespace Graffiti\Handlers;

use Graffiti\Http\Request;
use Graffiti\Http\Response;
use Graffiti\MessageRepository;
use Graffiti\SessionBag;

/**
 * GET /flagged — flagged message count for moderation widgets.
 * Auth: admin session, Authorization: Bearer <admin_password>, or X-Admin-Password.
 */
final class FlaggedCountHandler
{
    public function __construct(
        private MessageRepository $repo,
        private string $password,
        private SessionBag $session,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->isAuthorized($request)) {
            return $request->wantsPlainText()
                ? Response::plain('Forbidden.', 403)
                : Response::json(['error' => 'Forbidden'], 403);
        }

        $count = $this->repo->countFlagged();

        if ($this->wantsJson($request)) {
            return Response::json(['flagged' => $count]);
        }

        return Response::plain((string) $count);
    }

    private function isAuthorized(Request $request): bool
    {
        if ($this->session->isAdmin()) {
            return true;
        }

        $provided = $this->passwordFromRequest($request);
        return $provided !== '' && hash_equals($this->password, $provided);
    }

    private function passwordFromRequest(Request $request): string
    {
        $auth = $request->headers['Authorization'] ?? '';
        if (preg_match('/^Bearer\s+(\S+)/i', $auth, $matches) === 1) {
            return $matches[1];
        }

        $header = $request->headers['X-Admin-Password'] ?? '';
        if (is_string($header) && $header !== '') {
            return $header;
        }

        return '';
    }

    private function wantsJson(Request $request): bool
    {
        $accept = strtolower($request->headers['Accept'] ?? '');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        // Browsers / explicit JSON clients; curl defaults to plain count
        return !$request->wantsPlainText();
    }
}
