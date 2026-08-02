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

        if ($request->method === 'POST' && array_key_exists('id', $request->post)) {
            if (!$this->hasValidCsrfToken($request)) {
                return $this->csrfRejectedResponse($request);
            }

            $this->repo->delete((int) $request->post['id']);
            return Response::redirect('/admin');
        }

        return Response::html(($this->renderAdminPage)([
            'messages' => $this->repo->allNewestFirst(),
            'csrfToken' => $this->session->csrfToken(),
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

    private function csrfRejectedResponse(Request $request): Response
    {
        if ($request->wantsPlainText()) {
            return Response::plain('Forbidden.', 403);
        }

        return Response::html(($this->renderAdminPage)([
            'messages' => $this->repo->allNewestFirst(),
            'csrfToken' => $this->session->csrfToken(),
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
