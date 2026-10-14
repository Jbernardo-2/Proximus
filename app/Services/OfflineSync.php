<?php

namespace App\Services;

use App\Models\Device;

/**
 * Marks that the current work is the replay of an operation that already happened
 * on a device without connection, so business rules record exceptions instead of
 * rejecting facts the user can no longer undo (a sale taken, merchandise delivered).
 */
class OfflineSync
{
    private bool $active = false;

    private ?Device $device = null;

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    public function during(callable $callback, ?Device $device = null): mixed
    {
        [$previousActive, $previousDevice] = [$this->active, $this->device];
        $this->active = true;
        $this->device = $device;

        try {
            return $callback();
        } finally {
            [$this->active, $this->device] = [$previousActive, $previousDevice];
        }
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /** The registered phone the replayed operation comes from, if any. */
    public function device(): ?Device
    {
        return $this->active ? $this->device : null;
    }
}
