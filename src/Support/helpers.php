<?php

declare(strict_types=1);

use Lumina\DDD\Config\ConfigRepository;
use Lumina\DDD\Kernel;
use Lumina\DDD\Support\Env;

if (!function_exists('env')) {
    /**
     * Gets the value of an environment variable.
     *
     * @param string $key The environment variable name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    /**
     * Get or set configuration values.
     *
     * If an array is passed, values will be set. Otherwise, value will be retrieved.
     *
     * @param array<string, mixed>|string|null $key The config key or array of key-value pairs
     * @param mixed $default Default value for get operations
     * @return mixed|ConfigRepository
     */
    function config(array|string|null $key = null, mixed $default = null): mixed
    {
        $kernel = Kernel::getInstance();

        if ($kernel === null) {
            throw new RuntimeException('Kernel has not been initialized. Call Kernel::boot() first.');
        }

        $config = $kernel->getConfig();

        // Return repository if no key provided
        if ($key === null) {
            return $config;
        }

        // Set values if array provided
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $config->set($k, $v);
            }
            return null;
        }

        // Get value
        return $config->get($key, $default);
    }
}

if (!function_exists('app')) {
    /**
     * Get the application instance or resolve a service from the container.
     *
     * @template T of object
     * @param class-string<T>|string|null $abstract Service to resolve
     * @return T|Kernel|mixed
     */
    function app(?string $abstract = null): mixed
    {
        $kernel = Kernel::getInstance();

        if ($kernel === null) {
            throw new RuntimeException('Kernel has not been initialized. Call Kernel::boot() first.');
        }

        if ($abstract === null) {
            return $kernel;
        }

        return $kernel->make($abstract);
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the path to the base of the application.
     *
     * @param string $path Path to append
     * @return string
     */
    function base_path(string $path = ''): string
    {
        $kernel = Kernel::getInstance();

        if ($kernel === null) {
            throw new RuntimeException('Kernel has not been initialized. Call Kernel::boot() first.');
        }

        $basePath = $kernel->getBasePath();

        return $path !== '' ? $basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $basePath;
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the configuration path.
     *
     * @param string $path Path to append
     * @return string
     */
    function config_path(string $path = ''): string
    {
        $kernel = Kernel::getInstance();

        if ($kernel === null) {
            throw new RuntimeException('Kernel has not been initialized. Call Kernel::boot() first.');
        }

        $configPath = $kernel->getConfigPath();

        return $path !== '' ? $configPath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $configPath;
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the storage path.
     *
     * @param string $path Path to append
     * @return string
     */
    function storage_path(string $path = ''): string
    {
        $kernel = Kernel::getInstance();

        if ($kernel === null) {
            throw new RuntimeException('Kernel has not been initialized. Call Kernel::boot() first.');
        }

        $storagePath = $kernel->getStoragePath();

        return $path !== '' ? $storagePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $storagePath;
    }
}

if (!function_exists('value')) {
    /**
     * Return the default value of the given value.
     *
     * @template T
     * @param T|callable(): T $value
     * @param mixed ...$args Arguments to pass if value is callable
     * @return T
     */
    function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('blank')) {
    /**
     * Determine if the given value is "blank".
     *
     * @param mixed $value
     * @return bool
     */
    function blank(mixed $value): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || is_bool($value)) {
            return false;
        }

        if ($value instanceof Countable) {
            return count($value) === 0;
        }

        if ($value instanceof Stringable) {
            return trim((string) $value) === '';
        }

        return empty($value);
    }
}

if (!function_exists('filled')) {
    /**
     * Determine if a value is "filled".
     *
     * @param mixed $value
     * @return bool
     */
    function filled(mixed $value): bool
    {
        return !blank($value);
    }
}

if (!function_exists('class_basename')) {
    /**
     * Get the class "basename" of the given object / class.
     *
     * @param string|object $class
     * @return string
     */
    function class_basename(string|object $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value.
     *
     * @template T
     * @param T $value
     * @param (callable(T): mixed)|null $callback
     * @return T
     */
    function tap(mixed $value, ?callable $callback = null): mixed
    {
        if ($callback === null) {
            return $value;
        }

        $callback($value);

        return $value;
    }
}

if (!function_exists('with')) {
    /**
     * Return the given value, optionally passed through the given callback.
     *
     * @template T
     * @template TReturn
     * @param T $value
     * @param (callable(T): TReturn)|null $callback
     * @return ($callback is null ? T : TReturn)
     */
    function with(mixed $value, ?callable $callback = null): mixed
    {
        return $callback === null ? $value : $callback($value);
    }
}

if (!function_exists('transform')) {
    /**
     * Transform the given value if it is present.
     *
     * @template T
     * @template TReturn
     * @template TDefault
     * @param T $value
     * @param callable(T): TReturn $callback
     * @param TDefault|callable(T): TDefault $default
     * @return ($value is empty ? TDefault : TReturn)
     */
    function transform(mixed $value, callable $callback, mixed $default = null): mixed
    {
        if (filled($value)) {
            return $callback($value);
        }

        if (is_callable($default)) {
            return $default($value);
        }

        return $default;
    }
}

if (!function_exists('throw_if')) {
    /**
     * Throw the given exception if the given condition is true.
     *
     * @template T
     * @param T $condition
     * @param Throwable|class-string<Throwable>|string $exception
     * @param mixed ...$parameters
     * @return T
     *
     * @throws Throwable
     */
    function throw_if(mixed $condition, Throwable|string $exception = RuntimeException::class, mixed ...$parameters): mixed
    {
        if ($condition) {
            if ($exception instanceof Closure) {
                $exception = $exception(...$parameters);
            }

            if (is_string($exception) && class_exists($exception)) {
                $exception = new $exception(...$parameters);
            }

            throw is_string($exception) ? new RuntimeException($exception) : $exception;
        }

        return $condition;
    }
}

if (!function_exists('throw_unless')) {
    /**
     * Throw the given exception unless the given condition is true.
     *
     * @template T
     * @param T $condition
     * @param Throwable|class-string<Throwable>|string $exception
     * @param mixed ...$parameters
     * @return T
     *
     * @throws Throwable
     */
    function throw_unless(mixed $condition, Throwable|string $exception = RuntimeException::class, mixed ...$parameters): mixed
    {
        throw_if(!$condition, $exception, ...$parameters);

        return $condition;
    }
}

if (!function_exists('data_get')) {
    /**
     * Get an item from an array or object using "dot" notation.
     *
     * @param mixed $target
     * @param string|array<int, string>|null $key
     * @param mixed $default
     * @return mixed
     */
    function data_get(mixed $target, string|array|null $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $segment) {
            if ($segment === '*') {
                if (!is_iterable($target)) {
                    return value($default);
                }

                $result = [];

                foreach ($target as $item) {
                    $result[] = data_get($item, array_slice($key, 1));
                }

                return in_array('*', $key) ? array_merge(...$result) : $result;
            }

            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return value($default);
            }

            $key = array_slice($key, 1);
        }

        return $target;
    }
}

if (!function_exists('data_set')) {
    /**
     * Set an item on an array or object using "dot" notation.
     *
     * @param mixed $target
     * @param string|array<int, string> $key
     * @param mixed $value
     * @param bool $overwrite
     * @return mixed
     */
    function data_set(mixed &$target, string|array $key, mixed $value, bool $overwrite = true): mixed
    {
        $segments = is_array($key) ? $key : explode('.', $key);
        $segment = array_shift($segments);

        if ($segment === '*') {
            if (!is_array($target)) {
                $target = [];
            }

            if ($segments) {
                foreach ($target as &$inner) {
                    data_set($inner, $segments, $value, $overwrite);
                }
            } elseif ($overwrite) {
                foreach ($target as &$inner) {
                    $inner = $value;
                }
            }
        } elseif (is_array($target)) {
            if ($segments) {
                if (!array_key_exists($segment, $target)) {
                    $target[$segment] = [];
                }

                data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite || !array_key_exists($segment, $target)) {
                $target[$segment] = $value;
            }
        } elseif (is_object($target)) {
            if ($segments) {
                if (!isset($target->{$segment})) {
                    $target->{$segment} = [];
                }

                data_set($target->{$segment}, $segments, $value, $overwrite);
            } elseif ($overwrite || !isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        } else {
            $target = [];

            if ($segments) {
                data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite) {
                $target[$segment] = $value;
            }
        }

        return $target;
    }
}

if (!function_exists('windows_os')) {
    /**
     * Determine whether the current environment is Windows based.
     */
    function windows_os(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}

if (!function_exists('logger')) {
    /**
     * Get a logger instance or log a message.
     *
     * @param string|null $message Message to log (optional)
     * @param array<string, mixed> $context Context data
     * @param string|null $channel Channel to use (optional)
     * @return \Lumina\DDD\Logging\LoggerInterface|\Lumina\DDD\Logging\LogManager
     */
    function logger(?string $message = null, array $context = [], ?string $channel = null): mixed
    {
        $kernel = Kernel::getInstance();

        if ($kernel === null) {
            throw new RuntimeException('Kernel has not been initialized. Call Kernel::boot() first.');
        }

        /** @var \Lumina\DDD\Logging\LogManager $manager */
        $manager = $kernel->make(\Lumina\DDD\Logging\LogManager::class);

        if ($message === null) {
            return $channel !== null ? $manager->channel($channel) : $manager;
        }

        $logger = $channel !== null ? $manager->channel($channel) : $manager;
        $logger->debug($message, $context);

        return $manager;
    }
}

if (!function_exists('info')) {
    /**
     * Log an info message.
     *
     * @param string $message Message to log
     * @param array<string, mixed> $context Context data
     * @return void
     */
    function info(string $message, array $context = []): void
    {
        logger()->info($message, $context);
    }
}

if (!function_exists('mail')) {
    /**
     * Get a pending mail instance for fluent mail building.
     *
     * @param string|array<string>|null $to Recipients (optional)
     * @return \Lumina\DDD\Mail\PendingMail|\Lumina\DDD\Mail\Mailer
     */
    function mail(string|array|null $to = null): mixed
    {
        $kernel = Kernel::getInstance();

        if ($kernel === null) {
            throw new RuntimeException('Kernel has not been initialized. Call Kernel::boot() first.');
        }

        /** @var \Lumina\DDD\Mail\Mailer $mailer */
        $mailer = $kernel->make(\Lumina\DDD\Mail\Mailer::class);

        if ($to === null) {
            return $mailer;
        }

        return $mailer->to($to);
    }
}

if (!function_exists('dispatch')) {
    /**
     * Dispatch a job to the queue.
     *
     * @param \Lumina\DDD\Queue\JobInterface $job Job to dispatch
     * @param string|null $queue Queue name (optional)
     * @return string Job ID
     */
    function dispatch(\Lumina\DDD\Queue\JobInterface $job, ?string $queue = null): string
    {
        $kernel = Kernel::getInstance();

        if ($kernel === null) {
            throw new RuntimeException('Kernel has not been initialized. Call Kernel::boot() first.');
        }

        /** @var \Lumina\DDD\Queue\QueueManager $manager */
        $manager = $kernel->make(\Lumina\DDD\Queue\QueueManager::class);

        return $manager->push($job, $queue);
    }
}

if (!function_exists('dispatch_sync')) {
    /**
     * Dispatch a job synchronously (immediate execution).
     *
     * @param \Lumina\DDD\Queue\JobInterface $job Job to execute
     * @return void
     */
    function dispatch_sync(\Lumina\DDD\Queue\JobInterface $job): void
    {
        $kernel = Kernel::getInstance();

        if ($kernel === null) {
            throw new RuntimeException('Kernel has not been initialized. Call Kernel::boot() first.');
        }

        /** @var \Lumina\DDD\Queue\Drivers\SyncQueue $sync */
        $sync = new \Lumina\DDD\Queue\Drivers\SyncQueue($kernel->getContainer());
        $sync->push($job);
    }
}

if (!function_exists('validator')) {
    /**
     * Create a new validator instance.
     *
     * @param array<string, mixed> $data Data to validate
     * @param array<string, array<string|\Lumina\DDD\Validation\Rule>> $rules Validation rules
     * @param array<string, string> $messages Custom error messages
     * @return \Lumina\DDD\Validation\Validator
     */
    function validator(array $data, array $rules, array $messages = []): \Lumina\DDD\Validation\Validator
    {
        return new \Lumina\DDD\Validation\Validator($data, $rules, $messages);
    }
}

if (!function_exists('validate')) {
    /**
     * Validate data and return validated data or throw exception.
     *
     * @param array<string, mixed> $data Data to validate
     * @param array<string, array<string|\Lumina\DDD\Validation\Rule>> $rules Validation rules
     * @param array<string, string> $messages Custom error messages
     * @return array<string, mixed> Validated data
     * @throws \Lumina\DDD\Application\Validation\ValidationException
     */
    function validate(array $data, array $rules, array $messages = []): array
    {
        return validator($data, $rules, $messages)->validated();
    }
}

if (!function_exists('hash_make')) {
    /**
     * Hash a value (typically a password).
     *
     * @param string $value Value to hash
     * @param string|null $driver Hash driver to use (bcrypt, argon2id)
     * @return string Hashed value
     */
    function hash_make(string $value, ?string $driver = null): string
    {
        $kernel = Kernel::getInstance();

        if ($kernel === null) {
            throw new RuntimeException('Kernel has not been initialized. Call Kernel::boot() first.');
        }

        /** @var \Lumina\DDD\Security\HashManager $hash */
        $hash = $kernel->make(\Lumina\DDD\Security\HashManager::class);

        if ($driver !== null) {
            return $hash->driver($driver)->make($value);
        }

        return $hash->make($value);
    }
}

if (!function_exists('hash_check')) {
    /**
     * Verify a value against a hash.
     *
     * @param string $value Plain value
     * @param string $hashedValue Hashed value
     * @return bool True if value matches hash
     */
    function hash_check(string $value, string $hashedValue): bool
    {
        $kernel = Kernel::getInstance();

        if ($kernel === null) {
            throw new RuntimeException('Kernel has not been initialized. Call Kernel::boot() first.');
        }

        /** @var \Lumina\DDD\Security\HashManager $hash */
        $hash = $kernel->make(\Lumina\DDD\Security\HashManager::class);

        return $hash->check($value, $hashedValue);
    }
}

if (!function_exists('bcrypt')) {
    /**
     * Hash a value using Bcrypt.
     *
     * @param string $value Value to hash
     * @param int $rounds Cost factor (4-31)
     * @return string Hashed value
     */
    function bcrypt(string $value, int $rounds = 10): string
    {
        $hasher = new \Lumina\DDD\Security\BcryptHasher($rounds);
        return $hasher->make($value);
    }
}
