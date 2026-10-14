<?php

namespace App\Services;

/**
 * Marks that the current work is the replay of an operation that already happened
 * on a device without connection, so business rules record exceptions instead of
 * rejecting facts the user can no longer undo (a sale taken, merchandise delivered).
 */
class OfflineSync
{
    private bool $active = false;

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    public function during(callable $callback): mixed
    {
        $previous = $this->active;
        $this->active = true;

        try {
            return $callback();
        } finally {
            $this->active = $previous;
        }
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
