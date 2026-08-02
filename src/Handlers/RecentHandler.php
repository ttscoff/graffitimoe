<?php

declare(strict_types=1);

namespace Graffiti\Handlers;

use Graffiti\Http\Request;
use Graffiti\Http\Response;
use Graffiti\MessageRepository;

final class RecentHandler
{
    public function __construct(private MessageRepository $repo)
    {
    }

    public function handle(Request $request): Response
    {
        return Response::json($this->repo->recent(10));
    }
}
