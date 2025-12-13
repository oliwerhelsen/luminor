<?php

declare(strict_types=1);

namespace Luminor\DDD\Mail;

/**
 * Fluent interface for building and sending mail.
 *
 * Provides a fluent API for setting recipients and sending mailables.
 */
final class PendingMail
{
    /** @var array<string, string> */
    private array $to = [];

    /** @var array<string, string> */
    private array $cc = [];

    /** @var array<string, string> */
    private array $bcc = [];

    public function __construct(
        private readonly Mailer $mailer,
    ) {
    }

    /**
     * Set the recipients.
     *
     * @param string|array<string>|array<string, string> $to
     *
     * @return $this
     */
    public function to(string|array $to): self
    {
        if (is_string($to)) {
            $this->to[$to] = '';
        } else {
            foreach ($to as $key => $value) {
                if (is_int($key)) {
                    $this->to[$value] = '';
                } else {
                    $this->to[$key] = $value;
                }
            }
        }

        return $this;
    }

    /**
     * Set the CC recipients.
     *
     * @param string|array<string>|array<string, string> $cc
     *
     * @return $this
     */
    public function cc(string|array $cc): self
    {
        if (is_string($cc)) {
            $this->cc[$cc] = '';
        } else {
            foreach ($cc as $key => $value) {
                if (is_int($key)) {
                    $this->cc[$value] = '';
                } else {
                    $this->cc[$key] = $value;
                }
            }
        }

        return $this;
    }

    /**
     * Set the BCC recipients.
     *
     * @param string|array<string>|array<string, string> $bcc
     *
     * @return $this
     */
    public function bcc(string|array $bcc): self
    {
        if (is_string($bcc)) {
            $this->bcc[$bcc] = '';
        } else {
            foreach ($bcc as $key => $value) {
                if (is_int($key)) {
                    $this->bcc[$value] = '';
                } else {
                    $this->bcc[$key] = $value;
                }
            }
        }

        return $this;
    }

    /**
     * Send a mailable.
     *
     * @param Mailable $mailable The mailable to send
     */
    public function send(Mailable $mailable): bool
    {
        // Apply recipients to mailable
        foreach ($this->to as $address => $name) {
            $mailable->to($address, $name ?: null);
        }

        foreach ($this->cc as $address => $name) {
            $mailable->cc($address, $name ?: null);
        }

        foreach ($this->bcc as $address => $name) {
            $mailable->bcc($address, $name ?: null);
        }

        return $this->mailer->send($mailable);
    }

    /**
     * Queue a mailable for later sending.
     *
     * @param Mailable $mailable The mailable to queue
     */
    public function queue(Mailable $mailable): bool
    {
        // Apply recipients to mailable
        foreach ($this->to as $address => $name) {
            $mailable->to($address, $name ?: null);
        }

        foreach ($this->cc as $address => $name) {
            $mailable->cc($address, $name ?: null);
        }

        foreach ($this->bcc as $address => $name) {
            $mailable->bcc($address, $name ?: null);
        }

        return $this->mailer->queue($mailable);
    }
}
