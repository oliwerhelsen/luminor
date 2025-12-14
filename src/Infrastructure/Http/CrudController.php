<?php

declare(strict_types=1);

namespace Luminor\DDD\Infrastructure\Http;

use Luminor\DDD\Application\DTO\DataTransferObject;
use Luminor\DDD\Application\DTO\PagedResult;
use Luminor\DDD\Application\Services\CrudApplicationService;
use Luminor\DDD\Domain\Repository\AggregateNotFoundException;
use Luminor\DDD\Domain\Repository\Criteria;
use Luminor\DDD\Domain\Repository\Filter\EqualsFilter;
use Luminor\DDD\Domain\Repository\Pagination;
use Luminor\DDD\Domain\Repository\Sorting;
use Luminor\DDD\Http\Request;
use Luminor\DDD\Http\Response;

/**
 * Base controller for automatic CRUD endpoints.
 *
 * Provides standard REST endpoints for Create, Read, Update, Delete operations
 * by delegating to a CrudApplicationService.
 *
 * @template TDto of DataTransferObject
 * @template TCreateDto of DataTransferObject
 * @template TUpdateDto of DataTransferObject
 */
abstract class CrudController extends ApiController
{
    /**
     * @param CrudApplicationService<mixed, TDto, TCreateDto, TUpdateDto> $service
     */
    public function __construct(
        protected readonly CrudApplicationService $service
    ) {
    }

    /**
     * Get the name of the resource for messages.
     */
    abstract protected function getResourceName(): string;

    /**
     * Get the ID parameter name used in routes.
     */
    protected function getIdParamName(): string
    {
        return 'id';
    }

    /**
     * Get allowed fields for filtering.
     *
     * @return array<int, string>
     */
    protected function getAllowedFilterFields(): array
    {
        return [];
    }

    /**
     * Get allowed fields for sorting.
     *
     * @return array<int, string>
     */
    protected function getAllowedSortFields(): array
    {
        return [];
    }

    /**
     * Get the default sort field.
     */
    protected function getDefaultSortField(): ?string
    {
        return null;
    }

    /**
     * Get the default items per page.
     */
    protected function getDefaultPerPage(): int
    {
        return Pagination::DEFAULT_PER_PAGE;
    }

    /**
     * Get the maximum items per page.
     */
    protected function getMaxPerPage(): int
    {
        return Pagination::MAX_PER_PAGE;
    }

    /**
     * List all resources (paginated).
     *
     * GET /resources
     */
    public function index(Request $request, Response $response): void
    {
        $paginationParams = $this->getPaginationParams(
            $request,
            $this->getDefaultPerPage(),
            $this->getMaxPerPage()
        );

        $sortingParams = $this->getSortingParams(
            $request,
            $this->getAllowedSortFields(),
            $this->getDefaultSortField()
        );

        $criteria = $this->buildCriteriaFromRequest($request);

        if ($sortingParams['field'] !== null) {
            $sorting = Sorting::create(
                $sortingParams['field'],
                $sortingParams['direction'] === 'DESC' ? Sorting::DESC : Sorting::ASC
            );
            $criteria = $criteria->sortBy($sorting);
        }

        $result = $this->service->getPaginatedByCriteria(
            $criteria,
            $paginationParams['page'],
            $paginationParams['perPage']
        );

        $this->respondPaginated($response, $result);
    }

    /**
     * Get a single resource by ID.
     *
     * GET /resources/:id
     */
    public function show(Request $request, Response $response): void
    {
        $id = $this->getResourceId($request);

        try {
            $dto = $this->service->getByIdOrFail($id);
            $this->respondSuccess($response, $dto);
        } catch (AggregateNotFoundException $e) {
            $this->respondNotFound($response, "{$this->getResourceName()} not found");
        }
    }

    /**
     * Create a new resource.
     *
     * POST /resources
     */
    public function store(Request $request, Response $response): void
    {
        $data = $this->parseJsonBody($request);
        $createDto = $this->mapToCreateDto($data);

        $dto = $this->service->create($createDto);
        $this->respondCreated($response, $dto);
    }

    /**
     * Update an existing resource.
     *
     * PUT /resources/:id
     */
    public function update(Request $request, Response $response): void
    {
        $id = $this->getResourceId($request);
        $data = $this->parseJsonBody($request);

        try {
            $updateDto = $this->mapToUpdateDto($data);
            $dto = $this->service->update($id, $updateDto);
            $this->respondSuccess($response, $dto);
        } catch (AggregateNotFoundException $e) {
            $this->respondNotFound($response, "{$this->getResourceName()} not found");
        }
    }

    /**
     * Delete a resource.
     *
     * DELETE /resources/:id
     */
    public function destroy(Request $request, Response $response): void
    {
        $id = $this->getResourceId($request);

        try {
            $this->service->delete($id);
            $this->respondNoContent($response);
        } catch (AggregateNotFoundException $e) {
            $this->respondNotFound($response, "{$this->getResourceName()} not found");
        }
    }

    /**
     * Get the resource ID from the request.
     */
    protected function getResourceId(Request $request): mixed
    {
        return $this->getRequiredParam($request, $this->getIdParamName());
    }

    /**
     * Build a Criteria object from request parameters.
     */
    protected function buildCriteriaFromRequest(Request $request): Criteria
    {
        $criteria = Criteria::create();
        $allowedFields = $this->getAllowedFilterFields();

        foreach ($allowedFields as $field) {
            $value = $this->getOptionalParam($request, $field);
            if ($value !== null) {
                $criteria = $criteria->addFilter(new EqualsFilter($field, $value));
            }
        }

        return $criteria;
    }

    /**
     * Map request data to a create DTO.
     *
     * Override this method to customize DTO creation.
     *
     * @param array<string, mixed> $data
     * @return TCreateDto
     */
    abstract protected function mapToCreateDto(array $data): DataTransferObject;

    /**
     * Map request data to an update DTO.
     *
     * Override this method to customize DTO creation.
     *
     * @param array<string, mixed> $data
     * @return TUpdateDto
     */
    abstract protected function mapToUpdateDto(array $data): DataTransferObject;
}
