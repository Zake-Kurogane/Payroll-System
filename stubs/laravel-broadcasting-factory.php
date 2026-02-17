<?php

namespace Illuminate\Contracts\Broadcasting;

use Illuminate\Broadcasting\PendingBroadcast;

/**
 * Intelephense stub to reflect concrete BroadcastManager API.
 *
 * @method PendingBroadcast event(mixed $event = null)
 */
interface Factory
{
    /**
     * Get a broadcaster implementation by name.
     *
     * @param  string|null  $name
     * @return \Illuminate\Contracts\Broadcasting\Broadcaster
     */
    public function connection($name = null);
}
