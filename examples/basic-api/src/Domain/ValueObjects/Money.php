<?php

declare(strict_types=1);

namespace Example\BasicApi\Domain\ValueObjects;

use Luminor\DDD\Domain\Abstractions\ValueObject;

/**
 * Money value object demonstrating immutability and equality.
 */
final class Money extends ValueObject
{
    private function __construct(
        private readonly int $amount,
        private readonly string $currency,
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
        
        if (strlen($currency) !== 3) {
            throw new \InvalidArgumentException('Currency must be a 3-letter ISO code');
        }
    }

    /**
     * Create money from cents.
     */
    public static function fromCents(int $cents, string $currency = 'USD'): self
    {
        return new self($cents, strtoupper($currency));
    }

    /**
     * Create money from dollars (convenience method).
     */
    public static function fromDollars(float $dollars, string $currency = 'USD'): self
    {
        return new self((int) round($dollars * 100), strtoupper($currency));
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Get formatted amount (e.g., 19.99).
     */
    public function getFormatted(): string
    {
        return number_format($this->amount / 100, 2);
    }

    /**
     * Add two money values.
     */
    public function add(Money $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * Subtract money value.
     */
    public function subtract(Money $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->amount - $other->amount, $this->currency);
    }

    /**
     * Multiply by a factor.
     */
    public function multiply(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    protected function getEqualityComponents(): array
    {
        return [$this->amount, $this->currency];
    }

    private function ensureSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot operate on different currencies: %s and %s',
                $this->currency,
                $other->currency
            ));
        }
    }
}
