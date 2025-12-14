<?php

declare(strict_types=1);

namespace Luminor\Mail;

use Luminor\Queue\Job;

/**
 * Queue job for sending mail asynchronously.
 *
 * This job is used internally by the Mailer when queueing mailables.
 */
final class SendMailJob extends Job
{
    private Mailable $mailable;

    /** @var array<string, mixed> */
    private array $mailableData;

    /** @var class-string<Mailable> */
    private string $mailableClass;

    public function __construct(Mailable $mailable)
    {
        $this->mailable = $mailable;
        $this->mailableClass = get_class($mailable);
        $this->mailableData = $mailable->toArray();
    }

    /**
     * @inheritDoc
     */
    public function handle(): void
    {
        // Restore mailable from serialized data
        $mailable = $this->mailableClass::fromArray($this->mailableData);

        // Get the mailer from the container
        $mailer = $this->getMailer();

        // Send the mail
        $message = $mailable->build();
        $mailer->sendMessage($message);
    }

    /**
     * Get the mailer instance.
     *
     * @return Mailer
     */
    private function getMailer(): Mailer
    {
        // Try to get from container if available
        if (function_exists('app')) {
            return app(Mailer::class);
        }

        throw new \RuntimeException('Mailer not available. Ensure the container is configured.');
    }

    /**
     * @inheritDoc
     */
    protected function serialize(): array
    {
        return [
            'mailableClass' => $this->mailableClass,
            'mailableData' => $this->mailableData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected static function unserialize(array $data): static
    {
        $mailableClass = $data['mailableClass'];
        $mailableData = $data['mailableData'];

        /** @var Mailable $mailable */
        $mailable = $mailableClass::fromArray($mailableData);

        return new self($mailable);
    }
}
