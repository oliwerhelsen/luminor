<?php

declare(strict_types=1);

namespace Luminor\DDD\Session;

use Luminor\DDD\Session\Drivers\FileSessionDriver;
use Luminor\DDD\Session\Drivers\ArraySessionDriver;
use Luminor\DDD\Session\Drivers\DatabaseSessionDriver;
use Luminor\DDD\Database\ConnectionInterface;

/**
 * Session Manager
 *
 * Manages session drivers and provides a unified interface.
 */
final class SessionManager
{
    private Session $session;
    /** @var array<string, SessionDriver> */
    private array $drivers = [];
    private string $defaultDriver = 'file';

    public function __construct(?SessionDriver $driver = null, string $sessionName = 'luminor_session')
    {
        $driver = $driver ?? new FileSessionDriver(sys_get_temp_dir() . '/luminor_sessions');
        $this->session = new Session($driver, $sessionName);
    }

    /**
     * Create a file-based session.
     */
    public static function file(string $path, string $sessionName = 'luminor_session'): self
    {
        return new self(new FileSessionDriver($path), $sessionName);
    }

    /**
     * Create an array-based session (for testing).
     */
    public static function array(string $sessionName = 'luminor_session'): self
    {
        return new self(new ArraySessionDriver(), $sessionName);
    }

    /**
     * Create a database-based session.
     */
    public static function database(
        ConnectionInterface $connection,
        string $table = 'sessions',
        string $sessionName = 'luminor_session'
    ): self {
        return new self(new DatabaseSessionDriver($connection, $table), $sessionName);
    }

    /**
     * Get the session instance.
     */
    public function getSession(): Session
    {
        return $this->session;
    }

    /**
     * Start the session.
     */
    public function start(): bool
    {
        return $this->session->start();
    }

    /**
     * Save the session.
     */
    public function save(): void
    {
        $this->session->save();
    }

    /**
     * Forward calls to the session instance.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->session->$method(...$parameters);
    }
}
