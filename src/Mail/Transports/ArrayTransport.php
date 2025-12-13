<?php

declare(strict_types=1);

namespace Luminor\DDD\Mail\Transports;

use Luminor\DDD\Mail\Message;
use Luminor\DDD\Mail\TransportInterface;

/**
 * Array mail transport for testing.
 *
 * Stores all sent messages in memory for assertions in tests.
 */
final class ArrayTransport implements TransportInterface
{
    /** @var array<int, Message> */
    private array $messages = [];

    /**
     * @inheritDoc
     */
    public function send(Message $message): bool
    {
        $this->messages[] = $message;

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'array';
    }

    /**
     * Get all sent messages.
     *
     * @return array<int, Message>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the last sent message.
     */
    public function getLastMessage(): ?Message
    {
        if (empty($this->messages)) {
            return null;
        }

        return $this->messages[count($this->messages) - 1];
    }

    /**
     * Check if a message was sent to a specific address.
     */
    public function hasMessageTo(string $address): bool
    {
        foreach ($this->messages as $message) {
            if (array_key_exists($address, $message->getTo())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a message was sent with a specific subject.
     */
    public function hasMessageWithSubject(string $subject): bool
    {
        foreach ($this->messages as $message) {
            if ($message->getSubject() === $subject) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the number of sent messages.
     */
    public function count(): int
    {
        return count($this->messages);
    }

    /**
     * Clear all sent messages.
     */
    public function clear(): void
    {
        $this->messages = [];
    }
}
