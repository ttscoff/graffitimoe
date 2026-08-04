<?php

declare(strict_types=1);

namespace Graffiti\Handlers;

use Graffiti\Http\Request;
use Graffiti\Http\Response;
use Graffiti\MessageRepository;
use Graffiti\SessionBag;

final class AdminHandler
{
    /**
     * @param callable(array<string, mixed>): string $renderAdminPage
     * @param callable(array<string, mixed>): string $renderLoginPage
     */
    public function __construct(
        private MessageRepository $repo,
        private string $password,
        private SessionBag $session,
        private $renderAdminPage,
        private $renderLoginPage,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->session->isAdmin()) {
            return $this->handleUnauthenticated($request);
        }

        if ($request->method === 'POST' && array_key_exists('logout', $request->post)) {
            if (!$this->hasValidCsrfToken($request)) {
                return $this->csrfRejectedResponse($request, ($request->query['flagged'] ?? null) === '1');
            }

            $this->session->unset('admin');
            $this->session->regenerate();
            return Response::redirect('/admin');
        }

        if ($request->method === 'POST' && $this->hasBatchIds($request)) {
            if (!$this->hasValidCsrfToken($request)) {
                return $this->csrfRejectedResponse($request, ($request->query['flagged'] ?? null) === '1');
            }

            $ids = $this->batchIds($request);
            if (array_key_exists('batch_approve', $request->post)) {
                $this->repo->setFlaggedMany($ids, false);
            } elseif (array_key_exists('batch_delete', $request->post)) {
                $this->repo->deleteMany($ids);
            }

            return Response::redirect($this->safeNext($request));
        }

        if ($request->method === 'POST' && array_key_exists('id', $request->post)) {
            if (!$this->hasValidCsrfToken($request)) {
                return $this->csrfRejectedResponse($request, ($request->query['flagged'] ?? null) === '1');
            }

            $id = (int) $request->post['id'];
            if (array_key_exists('approve', $request->post)) {
                $this->repo->setFlagged($id, false);
            } else {
                $this->repo->delete($id);
            }

            return Response::redirect($this->safeNext($request));
        }

        $flaggedOnly = ($request->query['flagged'] ?? null) === '1';

        return Response::html(($this->renderAdminPage)([
            'messages' => $this->repo->allNewestFirst($flaggedOnly),
            'csrfToken' => $this->session->csrfToken(),
            'flaggedOnly' => $flaggedOnly,
        ]));
    }

    private function handleUnauthenticated(Request $request): Response
    {
        if ($request->method === 'POST' && array_key_exists('password', $request->post)) {
            if (!$this->hasValidCsrfToken($request)) {
                return $this->unauthorizedResponse($request, 'Invalid request. Please try again.');
            }

            $postedPassword = $request->post['password'];
            if (hash_equals((string) $this->password, (string) $postedPassword)) {
                $this->session->set('admin', 1);
                $this->session->regenerate();
                return Response::redirect('/admin');
            }

            return $this->unauthorizedResponse($request, 'Invalid password.');
        }

        return $this->unauthorizedResponse($request);
    }

    private function hasValidCsrfToken(Request $request): bool
    {
        $submitted = (string) ($request->post['csrf_token'] ?? '');

        return $submitted !== '' && hash_equals($this->session->csrfToken(), $submitted);
    }

    private function hasBatchIds(Request $request): bool
    {
        return array_key_exists('ids', $request->post)
            && (array_key_exists('batch_approve', $request->post) || array_key_exists('batch_delete', $request->post));
    }

    /** @return list<int> */
    private function batchIds(Request $request): array
    {
        $raw = $request->post['ids'] ?? [];
        if (!is_array($raw)) {
            $raw = [$raw];
        }
        $ids = [];
        foreach ($raw as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }

        return array_values($ids);
    }

    /** Allow only same-origin relative paths (block open redirects). */
    private function safeNext(Request $request): string
    {
        $next = (string) ($request->post['next'] ?? '/admin');
        if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, '\\')) {
            return '/admin';
        }

        return $next;
    }

    private function csrfRejectedResponse(Request $request, bool $flaggedOnly = false): Response
    {
        if ($request->wantsPlainText()) {
            return Response::plain('Forbidden.', 403);
        }

        return Response::html(($this->renderAdminPage)([
            'messages' => $this->repo->allNewestFirst($flaggedOnly),
            'csrfToken' => $this->session->csrfToken(),
            'flaggedOnly' => $flaggedOnly,
        ]), 403);
    }

    private function unauthorizedResponse(Request $request, ?string $error = null): Response
    {
        if ($request->wantsPlainText()) {
            return Response::plain('Forbidden.', 403);
        }

        return Response::html(($this->renderLoginPage)([
            'error' => $error,
            'csrfToken' => $this->session->csrfToken(),
        ]), $error === null ? 200 : 403);
    }
}
