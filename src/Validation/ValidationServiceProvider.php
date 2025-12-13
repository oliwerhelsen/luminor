<?php

declare(strict_types=1);

namespace Luminor\DDD\Validation;

use Luminor\DDD\Container\AbstractServiceProvider;

/**
 * Validation Service Provider
 *
 * Registers validation services in the container.
 */
final class ValidationServiceProvider extends AbstractServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register(): void
    {
        // Validator factory can be registered here if needed
        $this->container->singleton(ValidatorFactory::class, function ($container) {
            return new ValidatorFactory();
        });
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        // Boot logic if needed
    }
}
