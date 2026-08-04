<?php

declare(strict_types=1);

namespace Graffiti\Handlers;

use Graffiti\Color;
use Graffiti\Http\Request;
use Graffiti\Http\Response;
use Graffiti\MessageRepository;

final class RandomHandler
{
    public function __construct(
        private MessageRepository $repo,
        private string $baseUrl,
    ) {
    }

    public function handle(Request $request): Response
    {
        $row = $this->repo->random();
        if ($row === null) {
            $body = 'The wall is blank. Be the first: ' . rtrim($this->baseUrl, '/') . '/add';
            return Response::plain($body);
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
