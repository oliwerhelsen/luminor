<?php

declare(strict_types=1);

namespace Luminor\Mail;

/**
 * Marker interface for queueable mailables.
 *
 * Implement this interface on your Mailable class to have
 * it automatically queued instead of sent synchronously.
 */
interface ShouldQueue
{
    /**
     * Get the queue name for this mailable.
     *
     * @return string|null Null for default queue
     */
    public function queue(): ?string;

    /**
     * Get the delay in seconds before sending.
     *
     * @return int
     */
    public function delay(): int;

    /**
     * Get the queue connection name.
     *
     * @return string|null Null for default connection
     */
    public function connection(): ?string;
}
