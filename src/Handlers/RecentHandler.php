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
        $limit = $this->session->isAdmin()
            ? AddHandler::ADMIN_RECENT_LIMIT
            : AddHandler::PUBLIC_RECENT_LIMIT;

        return Response::json($this->repo->recent($limit));
    }
}
