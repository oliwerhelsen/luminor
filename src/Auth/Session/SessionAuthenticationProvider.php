<?php

declare(strict_types=1);

namespace Luminor\Auth\Session;

use Luminor\Auth\AuthenticatableInterface;
use Luminor\Auth\AuthenticationException;
use Luminor\Auth\AuthenticationProvider;
use Luminor\Http\Request;
use Luminor\Session\Session;

/**
 * Session-based Authentication Provider
 */
class SessionAuthenticationProvider implements AuthenticationProvider
{
    private Session $session;
    private $userResolver;
    private string $sessionKey;

    /**
     * @param Session $session
     * @param callable $userResolver Function that takes user ID and returns AuthenticatableInterface
     * @param string $sessionKey Key to store user ID in session
     */
    public function __construct(Session $session, callable $userResolver, string $sessionKey = 'user_id')
    {
        $this->session = $session;
        $this->userResolver = $userResolver;
        $this->sessionKey = $sessionKey;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(Request $request): ?AuthenticatableInterface
    {
        $userId = $this->session->get($this->sessionKey);

        if (!$userId) {
            return null;
        }

        try {
            $user = ($this->userResolver)($userId);

            if (!$user) {
                // User no longer exists, clear session
                $this->logout();
                throw AuthenticationException::userNotFound();
            }

            return $user;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new AuthenticationException("Session authentication failed: " . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request): bool
    {
        // Session provider always supports requests if session is active
        return $this->session->has($this->sessionKey);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'session';
    }

    /**
     * Log in a user (store in session)
     *
     * @param AuthenticatableInterface $user
     * @param bool $remember Whether to remember the user
     * @return void
     */
    public function login(AuthenticatableInterface $user, bool $remember = false): void
    {
        // Regenerate session ID to prevent session fixation
        $this->session->regenerate();

        // Store user ID in session
        $this->session->put($this->sessionKey, $user->getAuthIdentifier());

        // Handle "remember me" functionality
        if ($remember) {
            $this->setRememberToken($user);
        }
    }

    /**
     * Log out the current user
     *
     * @return void
     */
    public function logout(): void
    {
        $this->session->forget($this->sessionKey);
        $this->session->regenerate();
    }

    /**
     * Validate user credentials and log in if valid
     *
     * @param array $credentials ['email' => '...', 'password' => '...']
     * @param bool $remember
     * @return AuthenticatableInterface
     * @throws AuthenticationException
     */
    public function attempt(array $credentials, bool $remember = false): AuthenticatableInterface
    {
        if (!isset($credentials['email']) || !isset($credentials['password'])) {
            throw AuthenticationException::invalidCredentials();
        }

        $user = ($this->userResolver)($credentials['email'], true); // true = by email

        if (!$user) {
            throw AuthenticationException::invalidCredentials();
        }

        // Verify password
        $hashedPassword = $user->getAuthPassword();

        if (!$hashedPassword || !password_verify($credentials['password'], $hashedPassword)) {
            throw AuthenticationException::invalidCredentials();
        }

        $this->login($user, $remember);

        return $user;
    }

    /**
     * Set remember token for user
     *
     * @param AuthenticatableInterface $user
     * @return void
     */
    private function setRememberToken(AuthenticatableInterface $user): void
    {
        $token = bin2hex(random_bytes(32));
        $user->setRememberToken($token);

        // Store remember token in cookie (valid for 30 days)
        setcookie(
            'remember_token',
            $token,
            [
                'expires' => time() + (86400 * 30),
                'path' => '/',
                'httponly' => true,
                'secure' => true,
                'samesite' => 'Lax',
            ]
        );
    }
}
