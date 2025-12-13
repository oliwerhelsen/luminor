<?php

declare(strict_types=1);

namespace Luminor\DDD\Mail\Transports;

use Luminor\DDD\Mail\Message;
use Luminor\DDD\Mail\TransportInterface;
use RuntimeException;

/**
 * SMTP mail transport.
 *
 * Sends emails via SMTP using native PHP sockets.
 * Supports TLS/STARTTLS encryption.
 */
final class SmtpTransport implements TransportInterface
{
    private string $host;

    private int $port;

    private ?string $username;

    private ?string $password;

    private string $encryption;

    private int $timeout;

    /** @var resource|null */
    private $socket = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->host = $config['host'] ?? 'localhost';
        $this->port = $config['port'] ?? 587;
        $this->username = $config['username'] ?? null;
        $this->password = $config['password'] ?? null;
        $this->encryption = $config['encryption'] ?? 'tls';
        $this->timeout = $config['timeout'] ?? 30;
    }

    /**
     * @inheritDoc
     */
    public function send(Message $message): bool
    {
        if (! $message->hasRecipients()) {
            throw new RuntimeException('Message has no recipients.');
        }

        $this->connect();

        try {
            $this->sendHelo();

            if ($this->encryption === 'tls') {
                $this->startTls();
                $this->sendHelo();
            }

            if ($this->username && $this->password) {
                $this->authenticate();
            }

            $this->sendMailFrom($message);
            $this->sendRecipients($message);
            $this->sendData($message);

            $this->sendCommand('QUIT');

            return true;
        } finally {
            $this->disconnect();
        }
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'smtp';
    }

    /**
     * Connect to the SMTP server.
     */
    private function connect(): void
    {
        $host = $this->host;

        if ($this->encryption === 'ssl') {
            $host = 'ssl://' . $host;
        }

        $this->socket = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);

        if (! $this->socket) {
            throw new RuntimeException(sprintf('Could not connect to SMTP server: %s (%d)', $errstr, $errno));
        }

        stream_set_timeout($this->socket, $this->timeout);

        $response = $this->getResponse();

        if (! str_starts_with($response, '220')) {
            throw new RuntimeException('SMTP server did not send greeting: ' . $response);
        }
    }

    /**
     * Disconnect from the SMTP server.
     */
    private function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Send HELO/EHLO command.
     */
    private function sendHelo(): void
    {
        $hostname = gethostname() ?: 'localhost';
        $response = $this->sendCommand('EHLO ' . $hostname);

        if (! str_starts_with($response, '250')) {
            // Fall back to HELO
            $response = $this->sendCommand('HELO ' . $hostname);

            if (! str_starts_with($response, '250')) {
                throw new RuntimeException('SMTP HELO failed: ' . $response);
            }
        }
    }

    /**
     * Start TLS encryption.
     */
    private function startTls(): void
    {
        $response = $this->sendCommand('STARTTLS');

        if (! str_starts_with($response, '220')) {
            throw new RuntimeException('SMTP STARTTLS failed: ' . $response);
        }

        $crypto = stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

        if (! $crypto) {
            throw new RuntimeException('Could not enable TLS encryption.');
        }
    }

    /**
     * Authenticate with the server.
     */
    private function authenticate(): void
    {
        $response = $this->sendCommand('AUTH LOGIN');

        if (! str_starts_with($response, '334')) {
            throw new RuntimeException('SMTP AUTH LOGIN failed: ' . $response);
        }

        $response = $this->sendCommand(base64_encode($this->username));

        if (! str_starts_with($response, '334')) {
            throw new RuntimeException('SMTP username rejected: ' . $response);
        }

        $response = $this->sendCommand(base64_encode($this->password));

        if (! str_starts_with($response, '235')) {
            throw new RuntimeException('SMTP authentication failed: ' . $response);
        }
    }

    /**
     * Send MAIL FROM command.
     */
    private function sendMailFrom(Message $message): void
    {
        $from = $message->getFromAddress();

        if (! $from) {
            throw new RuntimeException('Message has no from address.');
        }

        $response = $this->sendCommand('MAIL FROM:<' . $from . '>');

        if (! str_starts_with($response, '250')) {
            throw new RuntimeException('SMTP MAIL FROM rejected: ' . $response);
        }
    }

    /**
     * Send RCPT TO commands for all recipients.
     */
    private function sendRecipients(Message $message): void
    {
        $recipients = array_merge(
            array_keys($message->getTo()),
            array_keys($message->getCc()),
            array_keys($message->getBcc()),
        );

        foreach ($recipients as $recipient) {
            $response = $this->sendCommand('RCPT TO:<' . $recipient . '>');

            if (! str_starts_with($response, '250') && ! str_starts_with($response, '251')) {
                throw new RuntimeException('SMTP RCPT TO rejected for ' . $recipient . ': ' . $response);
            }
        }
    }

    /**
     * Send the message data.
     */
    private function sendData(Message $message): void
    {
        $response = $this->sendCommand('DATA');

        if (! str_starts_with($response, '354')) {
            throw new RuntimeException('SMTP DATA rejected: ' . $response);
        }

        $data = $this->buildMessageData($message);

        // Send the data (escape lines starting with .)
        $data = str_replace("\n.", "\n..", $data);

        fwrite($this->socket, $data . "\r\n.\r\n");

        $response = $this->getResponse();

        if (! str_starts_with($response, '250')) {
            throw new RuntimeException('SMTP message rejected: ' . $response);
        }
    }

    /**
     * Build the raw message data.
     */
    private function buildMessageData(Message $message): string
    {
        $headers = [];
        $boundary = bin2hex(random_bytes(16));

        // From
        $fromName = $message->getFromName();
        $fromAddress = $message->getFromAddress();
        $headers[] = $fromName
            ? sprintf('From: %s <%s>', $this->encodeHeader($fromName), $fromAddress)
            : sprintf('From: %s', $fromAddress);

        // To
        $toList = [];
        foreach ($message->getTo() as $address => $name) {
            $toList[] = $name
                ? sprintf('%s <%s>', $this->encodeHeader($name), $address)
                : $address;
        }
        $headers[] = 'To: ' . implode(', ', $toList);

        // CC
        if (! empty($message->getCc())) {
            $ccList = [];
            foreach ($message->getCc() as $address => $name) {
                $ccList[] = $name
                    ? sprintf('%s <%s>', $this->encodeHeader($name), $address)
                    : $address;
            }
            $headers[] = 'Cc: ' . implode(', ', $ccList);
        }

        // Reply-To
        if (! empty($message->getReplyTo())) {
            $replyToList = [];
            foreach ($message->getReplyTo() as $address => $name) {
                $replyToList[] = $name
                    ? sprintf('%s <%s>', $this->encodeHeader($name), $address)
                    : $address;
            }
            $headers[] = 'Reply-To: ' . implode(', ', $replyToList);
        }

        // Subject
        $headers[] = 'Subject: ' . $this->encodeHeader($message->getSubject());

        // Date
        $headers[] = 'Date: ' . date('r');

        // Message-ID
        $headers[] = sprintf('Message-ID: <%s@%s>', bin2hex(random_bytes(16)), gethostname() ?: 'localhost');

        // MIME
        $headers[] = 'MIME-Version: 1.0';

        // Priority
        $priority = $message->getPriority();
        if ($priority !== null) {
            $headers[] = 'X-Priority: ' . $priority;
        }

        // Custom headers
        foreach ($message->getHeaders() as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }

        // Body
        $html = $message->getHtml();
        $text = $message->getText();
        $attachments = $message->getAttachments();

        if (! empty($attachments)) {
            // Multipart with attachments
            $headers[] = sprintf('Content-Type: multipart/mixed; boundary="%s"', $boundary);
            $body = $this->buildMultipartBody($message, $boundary);
        } elseif ($html && $text) {
            // Alternative (HTML + text)
            $headers[] = sprintf('Content-Type: multipart/alternative; boundary="%s"', $boundary);
            $body = $this->buildAlternativeBody($html, $text, $boundary);
        } elseif ($html) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: quoted-printable';
            $body = quoted_printable_encode($html);
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: quoted-printable';
            $body = quoted_printable_encode($text ?? '');
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /**
     * Build multipart body with attachments.
     */
    private function buildMultipartBody(Message $message, string $boundary): string
    {
        $parts = [];

        // Text/HTML content
        $html = $message->getHtml();
        $text = $message->getText();

        if ($html || $text) {
            $contentBoundary = bin2hex(random_bytes(16));

            $content = "--{$boundary}\r\n";

            if ($html && $text) {
                $content .= "Content-Type: multipart/alternative; boundary=\"{$contentBoundary}\"\r\n\r\n";
                $content .= $this->buildAlternativeBody($html, $text, $contentBoundary);
            } elseif ($html) {
                $content .= "Content-Type: text/html; charset=UTF-8\r\n";
                $content .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
                $content .= quoted_printable_encode($html);
            } else {
                $content .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $content .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
                $content .= quoted_printable_encode($text ?? '');
            }

            $parts[] = $content;
        }

        // Attachments
        foreach ($message->getAttachments() as $attachment) {
            $path = $attachment['path'];
            $name = $attachment['name'] ?? basename($path);
            $mime = $attachment['mime'] ?? mime_content_type($path) ?: 'application/octet-stream';

            if (! file_exists($path)) {
                continue;
            }

            $part = "--{$boundary}\r\n";
            $part .= "Content-Type: {$mime}; name=\"{$name}\"\r\n";
            $part .= "Content-Transfer-Encoding: base64\r\n";
            $part .= "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n";
            $part .= chunk_split(base64_encode(file_get_contents($path)));

            $parts[] = $part;
        }

        return implode("\r\n", $parts) . "\r\n--{$boundary}--";
    }

    /**
     * Build alternative body (HTML + text).
     */
    private function buildAlternativeBody(string $html, string $text, string $boundary): string
    {
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($text) . "\r\n\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($html) . "\r\n\r\n";

        $body .= "--{$boundary}--";

        return $body;
    }

    /**
     * Encode a header value for non-ASCII characters.
     */
    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }

        return $value;
    }

    /**
     * Send a command and get the response.
     */
    private function sendCommand(string $command): string
    {
        fwrite($this->socket, $command . "\r\n");

        return $this->getResponse();
    }

    /**
     * Get the response from the server.
     */
    private function getResponse(): string
    {
        $response = '';

        while ($line = fgets($this->socket, 515)) {
            $response .= $line;

            // Check if this is the last line (no hyphen after code)
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return trim($response);
    }
}
