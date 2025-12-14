<?php

declare(strict_types=1);

namespace Luminor\Infrastructure\Http\Middleware;

use Luminor\Application\Validation\Rules;
use Luminor\Application\Validation\ValidationException;
use Luminor\Application\Validation\ValidationResult;
use Luminor\Infrastructure\Http\Response\ValidationErrorResponse;
use Luminor\Http\Request;
use Luminor\Http\Response;

/**
 * Middleware for validating incoming request data.
 *
 * Validates request parameters against defined rules and returns
 * a standardized validation error response if validation fails.
 */
final class ValidationMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, Rules> $rules Validation rules keyed by parameter name
     */
    public function __construct(
        private readonly array $rules = []
    ) {
    }

    /**
     * Create middleware with rules.
     *
     * @param array<string, Rules> $rules
     */
    public static function withRules(array $rules): self
    {
        return new self($rules);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, Response $response, callable $next): void
    {
        if (count($this->rules) === 0) {
            $next($request, $response);
            return;
        }

        $result = $this->validate($request);

        if (!$result->isValid()) {
            $this->respondValidationError($response, $result);
            return;
        }

        $next($request, $response);
    }

    /**
     * Validate the request against the rules.
     */
    private function validate(Request $request): ValidationResult
    {
        $result = ValidationResult::valid();

        foreach ($this->rules as $field => $rules) {
            $value = $this->getFieldValue($request, $field);
            $fieldResult = $rules->validate($value);

            if (!$fieldResult->isValid()) {
                foreach ($fieldResult->getErrorsFor($field) as $error) {
                    $result = $result->addError($field, $error);
                }
            }
        }

        return $result;
    }

    /**
     * Get a field value from the request.
     *
     * Supports both query parameters and JSON body fields.
     */
    private function getFieldValue(Request $request, string $field): mixed
    {
        // First try to get from params
        $value = $request->getParam($field);

        if ($value !== null) {
            return $value;
        }

        // Try to get from JSON body
        $body = $this->parseJsonBody($request);
        return $body[$field] ?? null;
    }

    /**
     * Parse JSON body from request.
     *
     * @return array<string, mixed>
     */
    private function parseJsonBody(Request $request): array
    {
        $body = $request->getPayload();

        if (empty($body)) {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
    }

    /**
     * Send a validation error response.
     */
    private function respondValidationError(Response $response, ValidationResult $result): void
    {
        $errorResponse = ValidationErrorResponse::fromValidationResult($result);
        $response->setStatusCode(422);
        $response->json($errorResponse->toArray());
    }
}
