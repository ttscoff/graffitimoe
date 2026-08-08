<?php

declare(strict_types=1);

namespace Graffiti\Handlers;

use Graffiti\Http\Request;
use Graffiti\Http\Response;
use Graffiti\MessageRepository;
use Graffiti\SessionBag;

final class RecentHandler
{
    public function __construct(
        private MessageRepository $repo,
        private SessionBag $session,
    ) {
    }

    public function handle(Request $request): Response
    {
        $beforeRaw = $request->query['before'] ?? null;
        $beforeId = null;
        if ($beforeRaw !== null && $beforeRaw !== '' && ctype_digit((string) $beforeRaw)) {
            $n = (int) $beforeRaw;
            if ($n > 0) {
                $beforeId = $n;
            }
        }

        $defaultLimit = $beforeId !== null
            ? AddHandler::PUBLIC_RECENT_LIMIT
            : ($this->session->isAdmin()
                ? AddHandler::ADMIN_RECENT_LIMIT
                : AddHandler::PUBLIC_RECENT_LIMIT);

        $limit = $defaultLimit;
        if (isset($request->query['limit']) && ctype_digit((string) $request->query['limit'])) {
            $limit = (int) $request->query['limit'];
        }
        $limit = max(1, min(50, $limit));

        return Response::json($this->repo->recent($limit, $beforeId));
    }
}
