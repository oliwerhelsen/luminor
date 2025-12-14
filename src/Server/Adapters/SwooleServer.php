<?php

declare(strict_types=1);

namespace Luminor\Server\Adapters;

use Luminor\Server\ServerInterface;
use Luminor\Server\ServerType;

/**
 * Swoole HTTP Server adapter.
 *
 * High-performance async server with coroutines support.
 * Requires the ext-swoole PHP extension to be installed.
 *
 * @see https://www.swoole.com/
 */
final class SwooleServer implements ServerInterface
{
    private bool $running = false;

    /** @var object|null Swoole HTTP Server instance */
    private ?object $server = null;

    public function getName(): string
    {
        return ServerType::SWOOLE->displayName();
    }

    public function isAvailable(): bool
    {
        return extension_loaded('swoole') || extension_loaded('openswoole');
    }

    public function start(string $host, int $port, string $documentRoot, array $options = []): void
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException(
                'Swoole extension is not installed. Install it with: pecl install swoole'
            );
        }

        $workerNum = $options['workers'] ?? (int) (swoole_cpu_num() * 2);
        $maxRequest = $options['max_requests'] ?? 10000;
        $documentRoot = realpath($documentRoot) ?: $documentRoot;

        /** @var \Swoole\Http\Server $server */
        $server = new \Swoole\Http\Server($host, $port);

        $server->set([
            'worker_num' => $workerNum,
            'max_request' => $maxRequest,
            'document_root' => $documentRoot,
            'enable_static_handler' => $options['static_handler'] ?? true,
            'static_handler_locations' => $options['static_locations'] ?? ['/assets', '/images', '/css', '/js'],
        ]);

        $entryPoint = $options['entry_point'] ?? $documentRoot . '/index.php';

        $server->on('request', function ($request, $response) use ($entryPoint, $documentRoot): void {
            // Set up superglobals for compatibility
            $this->setupSuperglobals($request, $documentRoot);

            try {
                ob_start();

                if (file_exists($entryPoint)) {
                    require $entryPoint;
                }

                $content = ob_get_clean();

                if ($content !== false && $content !== '') {
                    $response->end($content);
                }
            } catch (\Throwable $e) {
                $response->status(500);
                $response->end('Internal Server Error: ' . $e->getMessage());
            } finally {
                // Clean up superglobals
                $this->cleanupSuperglobals();
            }
        });

        $server->on('start', function ($server) use ($host, $port, $workerNum): void {
            echo sprintf(
                "Swoole HTTP Server started at http://%s:%d with %d workers\n",
                $host,
                $port,
                $workerNum
            );
        });

        $this->server = $server;
        $this->running = true;

        $server->start();

        $this->running = false;
    }

    public function stop(): void
    {
        if ($this->server !== null && method_exists($this->server, 'shutdown')) {
            $this->server->shutdown();
        }

        $this->running = false;
        $this->server = null;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function getRequirements(): array
    {
        return [
            'extensions' => ['swoole'],
            'binaries' => [],
            'packages' => [],
            'install_command' => 'pecl install swoole',
        ];
    }

    /**
     * Set up PHP superglobals from Swoole request.
     */
    private function setupSuperglobals(object $request, string $documentRoot): void
    {
        $_GET = $request->get ?? [];
        $_POST = $request->post ?? [];
        $_COOKIE = $request->cookie ?? [];
        $_FILES = $request->files ?? [];

        $_SERVER = [
            'REQUEST_METHOD' => $request->server['request_method'] ?? 'GET',
            'REQUEST_URI' => $request->server['request_uri'] ?? '/',
            'PATH_INFO' => $request->server['path_info'] ?? '/',
            'QUERY_STRING' => $request->server['query_string'] ?? '',
            'SERVER_PROTOCOL' => $request->server['server_protocol'] ?? 'HTTP/1.1',
            'SERVER_SOFTWARE' => 'Swoole',
            'DOCUMENT_ROOT' => $documentRoot,
            'HTTP_HOST' => $request->header['host'] ?? 'localhost',
            'HTTP_USER_AGENT' => $request->header['user-agent'] ?? '',
            'HTTP_ACCEPT' => $request->header['accept'] ?? '',
            'HTTP_ACCEPT_LANGUAGE' => $request->header['accept-language'] ?? '',
            'HTTP_ACCEPT_ENCODING' => $request->header['accept-encoding'] ?? '',
            'HTTP_CONNECTION' => $request->header['connection'] ?? '',
            'HTTP_CONTENT_TYPE' => $request->header['content-type'] ?? '',
            'HTTP_CONTENT_LENGTH' => $request->header['content-length'] ?? '',
            'REMOTE_ADDR' => $request->server['remote_addr'] ?? '127.0.0.1',
            'REMOTE_PORT' => $request->server['remote_port'] ?? 0,
        ];

        // Add custom headers
        foreach ($request->header ?? [] as $key => $value) {
            $headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            if (!isset($_SERVER[$headerKey])) {
                $_SERVER[$headerKey] = $value;
            }
        }
    }

    /**
     * Clean up superglobals after request.
     */
    private function cleanupSuperglobals(): void
    {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];
        $_SERVER = [];
    }
}
