<?php

declare(strict_types=1);

namespace Luminor\Mail;

use Luminor\Mail\Transports\ArrayTransport;
use Luminor\Mail\Transports\LogTransport;
use Luminor\Mail\Transports\SmtpTransport;
use Luminor\Queue\QueueManager;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Mail manager that handles sending emails.
 *
 * Supports multiple transport drivers and automatic queueing
 * for mailables implementing ShouldQueue.
 */
final class Mailer
{
    /** @var array<string, mixed> */
    private array $config;

    /** @var array<string, TransportInterface> */
    private array $transports = [];

    private string $defaultMailer;

    private ?QueueManager $queueManager = null;
    private ?LoggerInterface $logger = null;

    /** @var array<string, callable> */
    private array $customDrivers = [];

    /**
     * @param array<string, mixed> $config Mail configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->defaultMailer = $config['default'] ?? 'smtp';
    }

    /**
     * Set the queue manager for async sending.
     */
    public function setQueueManager(QueueManager $queueManager): self
    {
        $this->queueManager = $queueManager;
        return $this;
    }

    /**
     * Set the logger for log transport.
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Get a transport instance by name.
     *
     * @param string|null $name The mailer name (null for default)
     * @return TransportInterface
     */
    public function mailer(?string $name = null): TransportInterface
    {
        $name = $name ?? $this->defaultMailer;

        if (!isset($this->transports[$name])) {
            $this->transports[$name] = $this->resolveTransport($name);
        }

        return $this->transports[$name];
    }

    /**
     * Send a mailable.
     *
     * @param Mailable $mailable The mailable to send
     * @param string|null $mailer The mailer name (null for default)
     * @return bool
     */
    public function send(Mailable $mailable, ?string $mailer = null): bool
    {
        // Check if should be queued
        if ($mailable instanceof ShouldQueue && $this->queueManager !== null) {
            return $this->queue($mailable);
        }

        $message = $mailable->build();
        $this->applyDefaults($message);

        return $this->mailer($mailer)->send($message);
    }

    /**
     * Send a raw message.
     *
     * @param Message $message The message to send
     * @param string|null $mailer The mailer name (null for default)
     * @return bool
     */
    public function sendMessage(Message $message, ?string $mailer = null): bool
    {
        $this->applyDefaults($message);
        return $this->mailer($mailer)->send($message);
    }

    /**
     * Queue a mailable for later sending.
     *
     * @param Mailable $mailable The mailable to queue
     * @return bool
     */
    public function queue(Mailable $mailable): bool
    {
        if ($this->queueManager === null) {
            throw new RuntimeException('Queue manager not configured. Cannot queue mail.');
        }

        $job = new SendMailJob($mailable);

        if ($mailable instanceof ShouldQueue) {
            $delay = $mailable->delay();
            $queue = $mailable->queue();
            $connection = $mailable->connection();

            if ($delay > 0) {
                $this->queueManager->connection($connection)->later($job, $delay, $queue);
            } else {
                $this->queueManager->connection($connection)->push($job, $queue);
            }
        } else {
            $this->queueManager->push($job);
        }

        return true;
    }

    /**
     * Create a pending mail builder.
     *
     * @param string|array<string> $to Recipients
     * @return PendingMail
     */
    public function to(string|array $to): PendingMail
    {
        return (new PendingMail($this))->to($to);
    }

    /**
     * Register a custom transport driver.
     *
     * @param string $name The driver name
     * @param callable $callback Factory callback: fn(array $config) => TransportInterface
     */
    public function extend(string $name, callable $callback): self
    {
        $this->customDrivers[$name] = $callback;
        return $this;
    }

    /**
     * Resolve a transport by name.
     *
     * @param string $name The mailer name
     * @return TransportInterface
     * @throws RuntimeException If the mailer is not configured
     */
    private function resolveTransport(string $name): TransportInterface
    {
        $mailerConfig = $this->config['mailers'][$name] ?? null;

        if ($mailerConfig === null) {
            throw new RuntimeException(sprintf('Mailer [%s] is not configured.', $name));
        }

        $driver = $mailerConfig['transport'] ?? $name;

        return $this->createTransport($driver, $mailerConfig);
    }

    /**
     * Create a transport instance.
     *
     * @param string $driver The driver name
     * @param array<string, mixed> $config The driver configuration
     * @return TransportInterface
     * @throws RuntimeException If the driver is not supported
     */
    private function createTransport(string $driver, array $config): TransportInterface
    {
        // Check custom drivers first
        if (isset($this->customDrivers[$driver])) {
            return ($this->customDrivers[$driver])($config);
        }

        return match ($driver) {
            'smtp' => new SmtpTransport($config),
            'array', 'memory' => new ArrayTransport(),
            'log' => new LogTransport($config, $this->logger),
            default => throw new RuntimeException(sprintf('Mail driver [%s] is not supported.', $driver)),
        };
    }

    /**
     * Apply default from address if not set.
     */
    private function applyDefaults(Message $message): void
    {
        if ($message->getFromAddress() === null) {
            $from = $this->config['from'] ?? [];
            $address = $from['address'] ?? null;
            $name = $from['name'] ?? null;

            if ($address) {
                $message->from($address, $name);
            }
        }
    }

    /**
     * Get the default mailer name.
     *
     * @return string
     */
    public function getDefaultMailer(): string
    {
        return $this->defaultMailer;
    }

    /**
     * Set the default mailer name.
     *
     * @param string $name The mailer name
     */
    public function setDefaultMailer(string $name): void
    {
        $this->defaultMailer = $name;
    }
}
