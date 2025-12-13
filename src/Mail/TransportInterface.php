<?php

declare(strict_types=1);

namespace Luminor\DDD\Mail;

/**
 * Interface for mail transport implementations.
 *
 * Defines the contract for sending email messages through
 * various transport mechanisms (SMTP, API, etc.).
 */
interface TransportInterface
{
    /**
     * Send an email message.
     *
     * @param Message $message The message to send
     * @return bool Whether the message was sent successfully
     * @throws \RuntimeException If sending fails
     */
    public function send(Message $message): bool;

    /**
     * Get the transport name.
     *
     * @return string
     */
    public function getName(): string;
}
