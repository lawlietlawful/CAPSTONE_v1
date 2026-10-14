<?php

namespace App\Support;

use RuntimeException;

/** Sign-ins are paused (see LoginThrottle); $seconds until they reopen. */
class LoginLocked extends RuntimeException
{
    public function __construct(public readonly int $seconds, public readonly bool $device = false)
    {
        $minutes = (int) ceil($seconds / 60);
        $wait = $seconds < 60 ? "{$seconds} seconds" : ($minutes === 1 ? '1 minute' : "{$minutes} minutes");

        parent::__construct(($device ? 'Too many failed sign-ins from this device.' : 'Too many sign-in attempts.')
            . " Please try again in {$wait}.");
    }
}
