<?php

declare(strict_types=1);

namespace Luminor\DDD\Application\Validation;

use Luminor\DDD\Application\CQRS\Command;

/**
 * Validates commands using registered validators.
 *
 * The CommandValidator aggregates multiple validators and routes
 * validation to the appropriate validator based on the command type.
 */
final class CommandValidator
{
    /** @var array<class-string<Command>, ValidatorInterface<Command>> */
    private array $validators = [];

    /**
     * Register a validator for a command type.
     *
     * @param ValidatorInterface<Command> $validator
     */
    public function register(ValidatorInterface $validator): void
    {
        $this->validators[$validator->supports()] = $validator;
    }

    /**
     * Validate a command.
     *
     * @throws ValidationException If validation fails
     */
    public function validate(Command $command): void
    {
        $result = $this->getValidationResult($command);

        if ($result->isInvalid()) {
            throw ValidationException::fromResult($result);
        }
    }

    /**
     * Get the validation result without throwing an exception.
     */
    public function getValidationResult(Command $command): ValidationResult
    {
        $commandClass = $command::class;

        if (!isset($this->validators[$commandClass])) {
            return ValidationResult::valid();
        }

        return $this->validators[$commandClass]->validate($command);
    }

    /**
     * Check if the command is valid.
     */
    public function isValid(Command $command): bool
    {
        return $this->getValidationResult($command)->isValid();
    }

    /**
     * Check if a validator is registered for a command type.
     *
     * @param class-string<Command> $commandClass
     */
    public function hasValidator(string $commandClass): bool
    {
        return isset($this->validators[$commandClass]);
    }
}
