<?php

declare(strict_types=1);

namespace Example\ModularApp\Modules\Catalog\Domain\ValueObjects;

use Luminor\Domain\Abstractions\ValueObject;

/**
 * Money value object.
 */
final class Money extends ValueObject
{
    public function __construct(
        private readonly int $amount,
        private readonly string $currency = 'USD',
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
    }

    public static function fromCents(int $cents, string $currency = 'USD'): self
    {
        return new self($cents, strtoupper($currency));
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    protected function getEqualityComponents(): array
    {
        return [$this->amount, $this->currency];
    }
}
