<?php

declare(strict_types=1);

namespace Luminor\Auth;

use Luminor\Http\Request;

/**
 * Manages multiple authentication providers
 */
class AuthenticationManager
{
    /**
     * @var array<string, AuthenticationProvider>
     */
    private array $providers = [];

    /**
     * @var string|null
     */
    private ?string $defaultProvider = null;

    /**
     * Register an authentication provider
     *
     * @param AuthenticationProvider $provider
     * @param bool $setAsDefault
     * @return self
     */
    public function register(AuthenticationProvider $provider, bool $setAsDefault = false): self
    {
        $this->providers[$provider->getName()] = $provider;

        if ($setAsDefault || $this->defaultProvider === null) {
            $this->defaultProvider = $provider->getName();
        }

        return $this;
    }

    /**
     * Authenticate using the most appropriate provider
     *
     * @param Request $request
     * @return AuthenticatableInterface|null
     * @throws AuthenticationException
     */
    public function authenticate(Request $request): ?AuthenticatableInterface
    {
        // Try each provider in order
        foreach ($this->providers as $provider) {
            if ($provider->supports($request)) {
                return $provider->authenticate($request);
            }
        }

        // Fall back to default provider if no provider supports the request
        if ($this->defaultProvider && isset($this->providers[$this->defaultProvider])) {
            return $this->providers[$this->defaultProvider]->authenticate($request);
        }

        return null;
    }

    /**
     * Authenticate using a specific provider
     *
     * @param string $providerName
     * @param Request $request
     * @return AuthenticatableInterface|null
     * @throws AuthenticationException
     */
    public function authenticateWith(string $providerName, Request $request): ?AuthenticatableInterface
    {
        if (!isset($this->providers[$providerName])) {
            throw new \InvalidArgumentException("Authentication provider '{$providerName}' not found");
        }

        return $this->providers[$providerName]->authenticate($request);
    }

    /**
     * Get a specific provider
     *
     * @param string $name
     * @return AuthenticationProvider|null
     */
    public function getProvider(string $name): ?AuthenticationProvider
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * Get all registered providers
     *
     * @return array<string, AuthenticationProvider>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Set the default provider
     *
     * @param string $name
     * @return self
     */
    public function setDefaultProvider(string $name): self
    {
        if (!isset($this->providers[$name])) {
            throw new \InvalidArgumentException("Provider '{$name}' not registered");
        }

        $this->defaultProvider = $name;
        return $this;
    }
}
