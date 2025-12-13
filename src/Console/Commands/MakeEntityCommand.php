<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands;

use Luminor\DDD\Console\Input;

/**
 * Command to generate a new Entity class.
 *
 * Creates a new domain entity with identity handling
 * and basic DDD structure.
 */
final class MakeEntityCommand extends AbstractMakeCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('make:entity')
            ->setDescription('Create a new domain entity class')
            ->addArgument('name', [
                'description' => 'The name of the entity (e.g., User, Order, Product)',
                'required' => true,
            ])
            ->addOption('aggregate', [
                'shortcut' => 'a',
                'description' => 'Create as an aggregate root instead of a simple entity',
            ])
            ->addOption('path', [
                'shortcut' => 'p',
                'description' => 'Custom output path (relative to project root)',
            ])
            ->addOption('force', [
                'shortcut' => 'f',
                'description' => 'Overwrite existing file',
            ]);
    }

    /**
     * @inheritDoc
     */
    protected function getStubName(): string
    {
        return 'entity.stub';
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultDirectory(): string
    {
        return 'src/Domain/Entities';
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultNamespace(): string
    {
        return 'Domain\\Entities';
    }

    /**
     * @inheritDoc
     */
    protected function buildReplacements(Input $input): array
    {
        $isAggregate = $input->hasOption('aggregate');

        return [
            '{{ baseClass }}' => $isAggregate ? 'AggregateRoot' : 'Entity',
            '{{ baseClassImport }}' => $isAggregate
                ? 'Luminor\\DDD\\Domain\\Abstractions\\AggregateRoot'
                : 'Luminor\\DDD\\Domain\\Abstractions\\Entity',
            '{{baseClass}}' => $isAggregate ? 'AggregateRoot' : 'Entity',
            '{{baseClassImport}}' => $isAggregate
                ? 'Luminor\\DDD\\Domain\\Abstractions\\AggregateRoot'
                : 'Luminor\\DDD\\Domain\\Abstractions\\Entity',
        ];
    }
}
