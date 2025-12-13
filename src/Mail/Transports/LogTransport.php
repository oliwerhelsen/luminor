<?php

declare(strict_types=1);

namespace Luminor\DDD\Mail\Transports;

use Luminor\DDD\Mail\Message;
use Luminor\DDD\Mail\TransportInterface;
use Psr\Log\LoggerInterface;

/**
 * Log mail transport for development.
 *
 * Logs all messages instead of sending them.
 * Useful for development and debugging.
 */
final class LogTransport implements TransportInterface
{
    private ?LoggerInterface $logger;
    private string $channel;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [], ?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->channel = $config['channel'] ?? 'mail';
    }

    /**
     * Set the logger instance.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function send(Message $message): bool
    {
        $logMessage = sprintf(
            'Email would be sent: To: %s, Subject: %s',
            implode(', ', array_keys($message->getTo())),
            $message->getSubject()
        );

        $context = [
            'to' => $message->getTo(),
            'cc' => $message->getCc(),
            'bcc' => $message->getBcc(),
            'from' => [
                'address' => $message->getFromAddress(),
                'name' => $message->getFromName(),
            ],
            'subject' => $message->getSubject(),
            'has_html' => $message->getHtml() !== null,
            'has_text' => $message->getText() !== null,
            'attachments' => count($message->getAttachments()),
        ];

        if ($this->logger) {
            $this->logger->info($logMessage, $context);
        } else {
            // Fall back to error_log if no logger available
            error_log($logMessage . ' ' . json_encode($context));
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'log';
    }
}
