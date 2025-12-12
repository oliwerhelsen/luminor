# Mail

Lumina provides a clean, fluent API for sending emails with support for multiple transports and queue integration.

## Configuration

Configure your mail settings in `config/mail.php`:

```php
return [
    'default' => env('MAIL_MAILER', 'smtp'),
    
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 30,
        ],
        
        'log' => [
            'transport' => 'log',
            'channel' => 'mail',
        ],
        
        'array' => [
            'transport' => 'array',
        ],
    ],
    
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Lumina App'),
    ],
];
```

## Sending Mail

### Using the Helper Function

```php
// Simple email
mail('user@example.com')->send(new WelcomeEmail($user));

// With CC and BCC
mail('user@example.com')
    ->cc('manager@example.com')
    ->bcc('audit@example.com')
    ->send(new OrderConfirmation($order));
```

### Using the Mailer

```php
use Lumina\DDD\Mail\Mailer;

$mailer = app(Mailer::class);

$mailer->to('user@example.com')
    ->cc(['team@example.com'])
    ->send(new WelcomeEmail($user));
```

## Creating Mailables

### Using the Generator

```bash
php bin/lumina-ddd make:mail WelcomeEmail
php bin/lumina-ddd make:mail OrderConfirmation --queued
```

### Manual Creation

```php
<?php

namespace App\Mail;

use Lumina\DDD\Mail\Mailable;
use Lumina\DDD\Mail\Message;

final class WelcomeEmail extends Mailable
{
    public function __construct(
        private readonly User $user,
    ) {}

    public function build(): Message
    {
        return $this->subject("Welcome to Our App, {$this->user->name}!")
            ->from('welcome@myapp.com', 'My App')
            ->html($this->renderHtml())
            ->text($this->renderText());
    }

    private function renderHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome</title>
</head>
<body>
    <h1>Welcome, {$this->user->name}!</h1>
    <p>Thank you for joining our platform.</p>
    <a href="https://myapp.com/dashboard">Get Started</a>
</body>
</html>
HTML;
    }

    private function renderText(): string
    {
        return <<<TEXT
Welcome, {$this->user->name}!

Thank you for joining our platform.

Get started: https://myapp.com/dashboard
TEXT;
    }
}
```

## Message API

The `Message` class provides a fluent API for building emails:

```php
$message = new Message();

$message
    ->from('sender@example.com', 'Sender Name')
    ->to('recipient@example.com', 'Recipient Name')
    ->cc('cc@example.com')
    ->bcc('bcc@example.com')
    ->replyTo('reply@example.com')
    ->subject('Hello World')
    ->html('<h1>Hello</h1>')
    ->text('Hello')
    ->attach('/path/to/file.pdf', 'document.pdf', 'application/pdf')
    ->header('X-Custom-Header', 'value')
    ->priority(Message::PRIORITY_HIGH);
```

### Attachments

```php
// Attach a file
$message->attach('/path/to/invoice.pdf');

// Attach with custom name
$message->attach('/path/to/invoice.pdf', 'Invoice-2024.pdf');

// Attach with MIME type
$message->attach('/path/to/data.csv', 'report.csv', 'text/csv');

// Attach raw data
$message->attachData($pdfContent, 'invoice.pdf', 'application/pdf');
```

### Custom Headers

```php
$message->header('X-Mailgun-Tag', 'welcome-emails');
$message->header('X-Priority', '1');
```

## Queued Mail

For time-consuming email operations, queue your mailables:

### Implement ShouldQueue

```php
use Lumina\DDD\Mail\Mailable;
use Lumina\DDD\Mail\ShouldQueue;

final class MonthlyReport extends Mailable implements ShouldQueue
{
    public function __construct(
        private readonly array $reportData,
    ) {}

    public function build(): Message
    {
        return $this->subject('Your Monthly Report')
            ->html($this->generateReport());
    }
    
    private function generateReport(): string
    {
        // Time-consuming report generation...
    }
}
```

### Send Queued Mail

```php
// Will be automatically queued
mail('user@example.com')->send(new MonthlyReport($data));

// Or use the queue method explicitly
mail('user@example.com')->queue(new MonthlyReport($data));

// Queue with delay
mail('user@example.com')->later(60, new MonthlyReport($data));
```

## Mail Transports

### SMTP Transport

Native PHP SMTP implementation with TLS/STARTTLS support:

```php
'smtp' => [
    'transport' => 'smtp',
    'host' => 'smtp.example.com',
    'port' => 587,
    'encryption' => 'tls',  // 'tls', 'ssl', or null
    'username' => 'user@example.com',
    'password' => 'secret',
    'timeout' => 30,
    'local_domain' => 'myapp.com',  // EHLO domain
],
```

### Log Transport

Logs emails instead of sending (useful for development):

```php
'log' => [
    'transport' => 'log',
    'channel' => 'mail',  // Log channel to use
],
```

### Array Transport

Stores emails in memory (useful for testing):

```php
'array' => [
    'transport' => 'array',
],
```

## Testing Mail

Use the array transport to capture sent emails:

```php
use Lumina\DDD\Mail\Transports\ArrayTransport;

// Get the transport
$transport = app(ArrayTransport::class);

// Send an email
mail('test@example.com')->send(new WelcomeEmail($user));

// Assert email was sent
$messages = $transport->getMessages();
$this->assertCount(1, $messages);
$this->assertEquals('test@example.com', $messages[0]->getTo()[0]['address']);
$this->assertStringContains('Welcome', $messages[0]->getSubject());

// Clear for next test
$transport->clear();
```

## Mailable Methods

The `Mailable` base class provides these helper methods:

```php
class MyEmail extends Mailable
{
    public function build(): Message
    {
        return $this
            ->subject('My Subject')
            ->from('sender@example.com', 'Sender')
            ->replyTo('reply@example.com')
            ->html('<h1>Content</h1>')
            ->text('Content')
            ->attach('/path/to/file.pdf')
            ->priority(Message::PRIORITY_HIGH);
    }
}
```

## Environment Variables

Common mail environment variables:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="My Application"
```

## Service Provider

The `MailServiceProvider` registers:

- `Mailer::class` - The mailer singleton
- `TransportInterface::class` - The default transport

```php
// In your service provider
public function register(ContainerInterface $container): void
{
    $container->singleton(Mailer::class, function () use ($container) {
        $config = config('mail');
        return new Mailer($config, $container->get(QueueManager::class));
    });
}
```
