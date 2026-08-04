<?php

declare(strict_types=1);

namespace Graffiti;

/** Tracks message IDs created in the current browser session (self-delete). */
final class OwnedMessages
{
    private const KEY = 'owned_ids';

    public function __construct(private SessionBag $session)
    {
    }

    public function remember(int $id): void
    {
        if ($id <= 0) {
            return;
        }
        $ids = $this->ids();
        $ids[$id] = $id;
        $this->session->set(self::KEY, array_values($ids));
    }

    public function forget(int $id): void
    {
        $ids = $this->ids();
        unset($ids[$id]);
        $this->session->set(self::KEY, array_values($ids));
    }

    public function owns(int $id): bool
    {
        return isset($this->ids()[$id]);
    }

    /** @return array<int, int> id => id */
    public function ids(): array
    {
        $raw = $this->session->get(self::KEY);
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }
        return $ids;
    }

    /** @return list<int> */
    public function idList(): array
    {
        return array_values($this->ids());
    }
}
