<?php

declare(strict_types=1);

namespace Lumina\DDD\Mail;

/**
 * Represents an email message.
 *
 * Contains all the data needed to send an email including
 * recipients, subject, body, and attachments.
 */
final class Message
{
    /** @var array<string, string> */
    private array $to = [];

    /** @var array<string, string> */
    private array $cc = [];

    /** @var array<string, string> */
    private array $bcc = [];

    /** @var array<string, string> */
    private array $replyTo = [];

    private ?string $fromAddress = null;
    private ?string $fromName = null;

    private string $subject = '';
    private ?string $html = null;
    private ?string $text = null;

    /** @var array<int, array{path: string, name: string|null, mime: string|null}> */
    private array $attachments = [];

    /** @var array<string, string> */
    private array $headers = [];

    private ?int $priority = null;

    /**
     * Add a recipient.
     */
    public function to(string $address, ?string $name = null): self
    {
        $this->to[$address] = $name ?? '';
        return $this;
    }

    /**
     * Add a CC recipient.
     */
    public function cc(string $address, ?string $name = null): self
    {
        $this->cc[$address] = $name ?? '';
        return $this;
    }

    /**
     * Add a BCC recipient.
     */
    public function bcc(string $address, ?string $name = null): self
    {
        $this->bcc[$address] = $name ?? '';
        return $this;
    }

    /**
     * Set the reply-to address.
     */
    public function replyTo(string $address, ?string $name = null): self
    {
        $this->replyTo[$address] = $name ?? '';
        return $this;
    }

    /**
     * Set the from address.
     */
    public function from(string $address, ?string $name = null): self
    {
        $this->fromAddress = $address;
        $this->fromName = $name;
        return $this;
    }

    /**
     * Set the subject.
     */
    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set the HTML body.
     */
    public function html(string $html): self
    {
        $this->html = $html;
        return $this;
    }

    /**
     * Set the plain text body.
     */
    public function text(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    /**
     * Attach a file.
     */
    public function attach(string $path, ?string $name = null, ?string $mime = null): self
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
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set the priority (1 = highest, 5 = lowest).
     */
    public function priority(int $priority): self
    {
        $this->priority = max(1, min(5, $priority));
        return $this;
    }

    // Getters

    /**
     * @return array<string, string>
     */
    public function getTo(): array
    {
        return $this->to;
    }

    /**
     * @return array<string, string>
     */
    public function getCc(): array
    {
        return $this->cc;
    }

    /**
     * @return array<string, string>
     */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    /**
     * @return array<string, string>
     */
    public function getReplyTo(): array
    {
        return $this->replyTo;
    }

    public function getFromAddress(): ?string
    {
        return $this->fromAddress;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getHtml(): ?string
    {
        return $this->html;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    /**
     * @return array<int, array{path: string, name: string|null, mime: string|null}>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    /**
     * Check if the message has recipients.
     */
    public function hasRecipients(): bool
    {
        return !empty($this->to) || !empty($this->cc) || !empty($this->bcc);
    }

    /**
     * Serialize the message to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'replyTo' => $this->replyTo,
            'from' => [
                'address' => $this->fromAddress,
                'name' => $this->fromName,
            ],
            'subject' => $this->subject,
            'html' => $this->html,
            'text' => $this->text,
            'attachments' => $this->attachments,
            'headers' => $this->headers,
            'priority' => $this->priority,
        ];
    }

    /**
     * Create a message from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $message = new self();

        foreach ($data['to'] ?? [] as $address => $name) {
            $message->to($address, $name ?: null);
        }

        foreach ($data['cc'] ?? [] as $address => $name) {
            $message->cc($address, $name ?: null);
        }

        foreach ($data['bcc'] ?? [] as $address => $name) {
            $message->bcc($address, $name ?: null);
        }

        foreach ($data['replyTo'] ?? [] as $address => $name) {
            $message->replyTo($address, $name ?: null);
        }

        if (isset($data['from']['address'])) {
            $message->from($data['from']['address'], $data['from']['name'] ?? null);
        }

        if (isset($data['subject'])) {
            $message->subject($data['subject']);
        }

        if (isset($data['html'])) {
            $message->html($data['html']);
        }

        if (isset($data['text'])) {
            $message->text($data['text']);
        }

        foreach ($data['attachments'] ?? [] as $attachment) {
            $message->attach(
                $attachment['path'],
                $attachment['name'] ?? null,
                $attachment['mime'] ?? null
            );
        }

        foreach ($data['headers'] ?? [] as $name => $value) {
            $message->header($name, $value);
        }

        if (isset($data['priority'])) {
            $message->priority($data['priority']);
        }

        return $message;
    }
}
