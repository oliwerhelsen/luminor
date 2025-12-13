<?php

declare(strict_types=1);

/**
 * Mail Configuration
 *
 * Configure the mail drivers and default settings for your application.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option defines the default mailer that gets used when sending
    | emails. The mailer specified here should match one of the mailers
    | defined in the "mailers" configuration array.
    |
    */

    'default' => env('MAIL_MAILER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish to set a global "from" address for all emails sent by
    | your application. This will be used when no specific "from" address
    | is provided on the mailable.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Luminor App'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application.
    | Available transports: "smtp", "log", "array"
    |
    | For advanced features (DKIM, API transports), install symfony/mailer
    | and register a custom transport.
    |
    */

    'mailers' => [
        /*
        |--------------------------------------------------------------------------
        | SMTP Mailer
        |--------------------------------------------------------------------------
        |
        | The SMTP transport sends emails via an SMTP server. This is the most
        | common way to send emails in production.
        |
        */
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => (int) env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 30,
        ],

        /*
        |--------------------------------------------------------------------------
        | Log Mailer
        |--------------------------------------------------------------------------
        |
        | The log transport writes all emails to the log instead of sending
        | them. Useful for local development.
        |
        */
        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL', 'mail'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Array Mailer
        |--------------------------------------------------------------------------
        |
        | The array transport stores all emails in memory. Useful for testing
        | to assert that emails were "sent".
        |
        */
        'array' => [
            'transport' => 'array',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | If you are using Markdown based email rendering, you may configure your
    | theme and component paths here.
    |
    */

    'markdown' => [
        'theme' => 'default',
        'paths' => [
            // resource_path('views/vendor/mail'),
        ],
    ],
];
