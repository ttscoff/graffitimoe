<?php

declare(strict_types=1);

namespace Graffiti\Handlers;

use Graffiti\Color;
use Graffiti\Http\Request;
use Graffiti\Http\Response;
use Graffiti\MessageRepository;

/** GET /id/{id} — return one spray by numeric id. */
final class IdHandler
{
    public function __construct(
        private MessageRepository $repo,
    ) {
    }

    public function handle(Request $request, int $id): Response
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            return Response::plain('Not found.', 404);
        }

        $body = Color::wrapMessage(
            $row['body'],
            $row['color'],
            $row['bold'],
            $row['spans'],
            $request->colorEnabled(),
        );

        return Response::plain($body);
    }
}
