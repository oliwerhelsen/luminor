<?php

declare(strict_types=1);

namespace Luminor\DDD\Session;

/**
 * Session
 *
 * Manages session data with various storage drivers.
 */
final class Session implements SessionInterface
{
    private SessionDriver $driver;
    private string $name;
    private bool $started = false;
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(
        SessionDriver $driver,
        string $name = 'luminor_session'
    ) {
        $this->driver = $driver;
        $this->name = $name;
    }

    /**
     * @inheritDoc
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        // Load session data from driver
        $id = $this->getId();
        if ($id === '') {
            $id = $this->generateId();
            $this->setId($id);
        }

        $this->data = $this->driver->read($id);
        $this->started = true;

        return true;
    }

    /**
     * @inheritDoc
     */
    public function save(): void
    {
        if (!$this->started) {
            return;
        }

        $this->driver->write($this->getId(), $this->data);
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function put(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * @inheritDoc
     */
    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * @inheritDoc
     */
    public function flush(): void
    {
        $this->data = [];
    }

    /**
     * @inheritDoc
     */
    public function regenerate(bool $destroy = false): bool
    {
        if ($destroy) {
            $this->driver->destroy($this->getId());
        }

        $newId = $this->generateId();
        $this->setId($newId);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return $_COOKIE[$this->name] ?? '';
    }

    /**
     * @inheritDoc
     */
    public function setId(string $id): void
    {
        setcookie($this->name, $id, [
            'expires' => time() + 7200, // 2 hours
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function destroy(): bool
    {
        $this->data = [];
        $this->started = false;

        $result = $this->driver->destroy($this->getId());

        setcookie($this->name, '', time() - 3600, '/');

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Get the session name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Generate a new session ID.
     */
    private function generateId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Flash data for the next request.
     */
    public function flash(string $key, mixed $value): void
    {
        $this->put("_flash.new.{$key}", $value);
    }

    /**
     * Reflash all flash data for another request.
     */
    public function reflash(): void
    {
        $old = $this->get('_flash.old', []);
        foreach ($old as $key => $value) {
            $this->flash($key, $value);
        }
    }

    /**
     * Age the flash data (move from new to old).
     */
    public function ageFlashData(): void
    {
        $this->forget('_flash.old');

        $new = $this->get('_flash.new', []);
        $this->put('_flash.old', $new);
        $this->forget('_flash.new');
    }
}
