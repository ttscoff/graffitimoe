<?php

declare(strict_types=1);

namespace Graffiti;

/** Tracks message IDs flagged in the current browser session (UI state). */
final class FlaggedMessages
{
    private const KEY = 'flagged_ids';

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

    public function has(int $id): bool
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

    /** @param list<int> $ids */
    public function sync(array $ids): void
    {
        $clean = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $clean[$n] = $n;
            }
        }
        $this->session->set(self::KEY, array_values($clean));
    }
}
