<?php

declare(strict_types=1);

namespace Graffiti\Handlers;

use Graffiti\Color;
use Graffiti\FlaggedMessages;
use Graffiti\Http\Request;
use Graffiti\Http\Response;
use Graffiti\MessageRepository;
use Graffiti\OwnedMessages;
use Graffiti\RateLimiter;
use Graffiti\SessionBag;

/** GET /id/{id} — HTML solo page for browsers; plain text for curl/CLI. */
final class IdHandler
{
    /** @param callable(array<string, mixed>): string $renderIdPage */
    public function __construct(
        private MessageRepository $repo,
        private SessionBag $session,
        private OwnedMessages $owned,
        private FlaggedMessages $flagged,
        private string $ipSecret,
        private $renderIdPage,
    ) {
    }

    public function handle(Request $request, int $id): Response
    {
        $row = $this->repo->find($id);
        $wantsHtml = $request->isBrowser() && !$request->wantsPlainText();

        if ($row === null) {
            if ($wantsHtml) {
                return Response::html(($this->renderIdPage)($this->pageVars(null, $request)), 404);
            }

            return Response::plain('Not found.', 404);
        }

        if ($wantsHtml) {
            return Response::html(($this->renderIdPage)($this->pageVars($row, $request)));
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

    /**
     * @param array{id:int,body:string,color:string,bold:bool,spans:list<array{t:string,c:string,b?:bool}>|null,flagged:bool,created_at:string}|null $message
     * @return array<string, mixed>
     */
    private function pageVars(?array $message, Request $request): array
    {
        $flaggedIds = [];
        if ($message !== null) {
            $ipHash = RateLimiter::hashIp($request->ip, $this->ipSecret);
            $flaggedIds = $this->repo->flaggedMessageIdsForIp([(int) $message['id']], $ipHash);
            $this->flagged->sync($flaggedIds);
            $flaggedIds = $this->flagged->idList();
        }

        return [
            'message' => $message,
            'isAdmin' => $this->session->isAdmin(),
            'ownedIds' => $this->owned->idList(),
            'csrfToken' => $this->session->csrfToken(),
            'flaggedIds' => $flaggedIds,
        ];
    }
}
