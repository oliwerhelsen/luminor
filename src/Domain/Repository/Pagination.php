<?php

declare(strict_types=1);

namespace Luminor\Domain\Repository;

use InvalidArgumentException;

/**
 * Value object representing pagination configuration.
 */
final class Pagination
{
    public const DEFAULT_PAGE = 1;
    public const DEFAULT_PER_PAGE = 25;
    public const MAX_PER_PAGE = 100;

    private function __construct(
        private readonly int $page,
        private readonly int $perPage
    ) {
    }

    /**
     * Create pagination for a specific page.
     *
     * @throws InvalidArgumentException If page or perPage is invalid
     */
    public static function create(int $page = self::DEFAULT_PAGE, int $perPage = self::DEFAULT_PER_PAGE): self
    {
        if ($page < 1) {
            throw new InvalidArgumentException('Page must be greater than 0');
        }

        if ($perPage < 1) {
            throw new InvalidArgumentException('Per page must be greater than 0');
        }

        if ($perPage > self::MAX_PER_PAGE) {
            throw new InvalidArgumentException(
                sprintf('Per page cannot exceed %d', self::MAX_PER_PAGE)
            );
        }

        return new self($page, $perPage);
    }

    /**
     * Create pagination with offset and limit.
     *
     * @throws InvalidArgumentException If offset or limit is invalid
     */
    public static function fromOffsetLimit(int $offset, int $limit): self
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be non-negative');
        }

        if ($limit < 1) {
            throw new InvalidArgumentException('Limit must be greater than 0');
        }

        if ($limit > self::MAX_PER_PAGE) {
            throw new InvalidArgumentException(
                sprintf('Limit cannot exceed %d', self::MAX_PER_PAGE)
            );
        }

        $page = (int) floor($offset / $limit) + 1;
        return new self($page, $limit);
    }

    /**
     * Create pagination for the first page.
     */
    public static function firstPage(int $perPage = self::DEFAULT_PER_PAGE): self
    {
        return self::create(1, $perPage);
    }

    /**
     * Create a "no pagination" instance that retrieves all records.
     * Be cautious when using this with large datasets.
     */
    public static function none(): self
    {
        return new self(1, self::MAX_PER_PAGE);
    }

    /**
     * Get the current page number.
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Get the number of items per page.
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Get the offset for database queries.
     */
    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Get the limit for database queries (alias for getPerPage).
     */
    public function getLimit(): int
    {
        return $this->perPage;
    }

    /**
     * Get pagination for the next page.
     */
    public function nextPage(): self
    {
        return new self($this->page + 1, $this->perPage);
    }

    /**
     * Get pagination for the previous page.
     *
     * @throws InvalidArgumentException If already on the first page
     */
    public function previousPage(): self
    {
        if ($this->page <= 1) {
            throw new InvalidArgumentException('Already on the first page');
        }

        return new self($this->page - 1, $this->perPage);
    }

    /**
     * Check if there is a previous page.
     */
    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }

    /**
     * Check if this pagination equals another.
     */
    public function equals(?Pagination $other): bool
    {
        if ($other === null) {
            return false;
        }

        return $this->page === $other->page && $this->perPage === $other->perPage;
    }
}
