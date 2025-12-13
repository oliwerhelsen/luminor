<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Bus\Middleware;

use Luminor\DDD\Application\CQRS\Command;
use Luminor\DDD\Application\CQRS\Query;
use Luminor\DDD\Infrastructure\Bus\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Throwable;

/**
 * Middleware that logs command/query execution.
 */
final class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Command|Query $message, callable $next): mixed
    {
        $messageType = $message instanceof Command ? 'Command' : 'Query';
        $messageClass = $message::class;

        $this->logger->info(sprintf('%s dispatched: %s', $messageType, $messageClass), [
            'message' => $this->serializeMessage($message),
        ]);

        $startTime = microtime(true);

        try {
            $result = $next($message);

            $duration = microtime(true) - $startTime;
            $this->logger->info(sprintf('%s completed: %s', $messageType, $messageClass), [
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $result;
        } catch (Throwable $e) {
            $duration = microtime(true) - $startTime;
            $this->logger->error(sprintf('%s failed: %s', $messageType, $messageClass), [
                'duration_ms' => round($duration * 1000, 2),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Serialize message for logging.
     *
     * @return array<string, mixed>
     */
    private function serializeMessage(Command|Query $message): array
    {
        $reflection = new ReflectionClass($message);
        $properties = $reflection->getProperties();
        $data = [];

        foreach ($properties as $property) {
            $value = $property->getValue($message);

            // Mask sensitive data
            if ($this->isSensitiveProperty($property->getName())) {
                $value = '***MASKED***';
            }

            $data[$property->getName()] = $value;
        }

        return $data;
    }

    /**
     * Check if a property name suggests sensitive data.
     */
    private function isSensitiveProperty(string $name): bool
    {
        $sensitivePatterns = ['password', 'secret', 'token', 'key', 'credential'];

        foreach ($sensitivePatterns as $pattern) {
            if (stripos($name, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
