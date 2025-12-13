<?php

declare(strict_types=1);

namespace Luminor\DDD\Mail;

/**
 * Abstract base class for mailable messages.
 *
 * Extend this class to create structured, reusable email messages.
 * Override the build() method to configure the message.
 */
abstract class Mailable
{
    protected ?string $fromAddress = null;

    protected ?string $fromName = null;

    protected ?string $replyToAddress = null;

    protected ?string $replyToName = null;

    protected string $subject = '';

    /** @var array<string, string> */
    protected array $to = [];

    /** @var array<string, string> */
    protected array $cc = [];

    /** @var array<string, string> */
    protected array $bcc = [];

    /** @var array<int, array{path: string, name: string|null, mime: string|null}> */
    protected array $attachments = [];

    /** @var array<string, string> */
    protected array $headers = [];

    protected ?int $priority = null;

    /**
     * Build the message.
     *
     * Override this method to configure the mailable.
     */
    abstract public function build(): Message;

    /**
     * Set the from address.
     */
    public function from(string $address, ?string $name = null): static
    {
        $this->fromAddress = $address;
        $this->fromName = $name;

        return $this;
    }

    /**
     * Set the reply-to address.
     */
    public function replyTo(string $address, ?string $name = null): static
    {
        $this->replyToAddress = $address;
        $this->replyToName = $name;

        return $this;
    }

    /**
     * Set the subject.
     */
    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Add a recipient.
     */
    public function to(string $address, ?string $name = null): static
    {
        $this->to[$address] = $name ?? '';

        return $this;
    }

    /**
     * Add a CC recipient.
     */
    public function cc(string $address, ?string $name = null): static
    {
        $this->cc[$address] = $name ?? '';

        return $this;
    }

    /**
     * Add a BCC recipient.
     */
    public function bcc(string $address, ?string $name = null): static
    {
        $this->bcc[$address] = $name ?? '';

        return $this;
    }

    /**
     * Attach a file.
     */
    public function attach(string $path, ?string $name = null, ?string $mime = null): static
    {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name,
            'mime' => $mime,
        ];

        return $this;
    }

    /**
     * Add a custom header.
     */
    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Set the priority (1 = highest, 5 = lowest).
     */
    public function priority(int $priority): static
    {
        $this->priority = max(1, min(5, $priority));

        return $this;
    }

    /**
     * Build a base message with common properties.
     */
    protected function buildMessage(): Message
    {
        $message = new Message();

        if ($this->fromAddress) {
            $message->from($this->fromAddress, $this->fromName);
        }

        if ($this->replyToAddress) {
            $message->replyTo($this->replyToAddress, $this->replyToName);
        }

        if ($this->subject) {
            $message->subject($this->subject);
        }

        foreach ($this->to as $address => $name) {
            $message->to($address, $name ?: null);
        }

        foreach ($this->cc as $address => $name) {
            $message->cc($address, $name ?: null);
        }

        foreach ($this->bcc as $address => $name) {
            $message->bcc($address, $name ?: null);
        }

        foreach ($this->attachments as $attachment) {
            $message->attach(
                $attachment['path'],
                $attachment['name'],
                $attachment['mime'],
            );
        }

        foreach ($this->headers as $name => $value) {
            $message->header($name, $value);
        }

        if ($this->priority !== null) {
            $message->priority($this->priority);
        }

        return $message;
    }

    /**
     * Render an HTML view for the email body.
     *
     * Override this method to use a template engine.
     *
     * @param string $template Template path or content
     * @param array<string, mixed> $data Data to pass to the template
     */
    protected function render(string $template, array $data = []): string
    {
        // Basic variable replacement for simple templates
        // Override for full template engine support
        $html = $template;

        foreach ($data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $html = str_replace('{{ ' . $key . ' }}', (string) $value, $html);
                $html = str_replace('{{' . $key . '}}', (string) $value, $html);
            }
        }

        return $html;
    }

    /**
     * Serialize the mailable for queueing.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'class' => static::class,
            'from' => [
                'address' => $this->fromAddress,
                'name' => $this->fromName,
            ],
            'replyTo' => [
                'address' => $this->replyToAddress,
                'name' => $this->replyToName,
            ],
            'subject' => $this->subject,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'attachments' => $this->attachments,
            'headers' => $this->headers,
            'priority' => $this->priority,
            'data' => $this->getSerializableData(),
        ];
    }

    /**
     * Create a mailable from serialized data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static
    {
        $mailable = new static();

        if (isset($data['from']['address'])) {
            $mailable->from($data['from']['address'], $data['from']['name'] ?? null);
        }

        if (isset($data['replyTo']['address'])) {
            $mailable->replyTo($data['replyTo']['address'], $data['replyTo']['name'] ?? null);
        }

        if (isset($data['subject'])) {
            $mailable->subject($data['subject']);
        }

        foreach ($data['to'] ?? [] as $address => $name) {
            $mailable->to($address, $name ?: null);
        }

        foreach ($data['cc'] ?? [] as $address => $name) {
            $mailable->cc($address, $name ?: null);
        }

        foreach ($data['bcc'] ?? [] as $address => $name) {
            $mailable->bcc($address, $name ?: null);
        }

        foreach ($data['attachments'] ?? [] as $attachment) {
            $mailable->attach(
                $attachment['path'],
                $attachment['name'] ?? null,
                $attachment['mime'] ?? null,
            );
        }

        foreach ($data['headers'] ?? [] as $name => $value) {
            $mailable->header($name, $value);
        }

        if (isset($data['priority'])) {
            $mailable->priority($data['priority']);
        }

        $mailable->restoreSerializableData($data['data'] ?? []);

        return $mailable;
    }

    /**
     * Get serializable data for queueing.
     *
     * Override this method to include custom properties.
     *
     * @return array<string, mixed>
     */
    protected function getSerializableData(): array
    {
        return [];
    }

    /**
     * Restore serializable data from queue.
     *
     * Override this method to restore custom properties.
     *
     * @param array<string, mixed> $data
     */
    protected function restoreSerializableData(array $data): void
    {
        // Override in subclass
    }
}
