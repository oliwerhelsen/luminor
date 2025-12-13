<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP82Migration' => true,

        // Import rules
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],

        // Array rules
        'array_syntax' => ['syntax' => 'short'],
        'trim_array_spaces' => true,
        'no_whitespace_before_comma_in_array' => true,
        'whitespace_after_comma_in_array' => true,

        // Class rules
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'one',
                'method' => 'one',
                'property' => 'one',
            ],
        ],
        'no_blank_lines_after_class_opening' => true,
        'single_class_element_per_statement' => true,

        // Spacing rules
        'binary_operator_spaces' => ['default' => 'single_space'],
        'concat_space' => ['spacing' => 'one'],
        'not_operator_with_successor_space' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'curly_brace_block',
                'extra',
                'parenthesis_brace_block',
                'square_brace_block',
                'throw',
                'use',
            ],
        ],

        // Other rules
        'declare_strict_types' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        'no_trailing_comma_in_singleline' => true,
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_separation' => true,
        'phpdoc_trim' => true,
        'phpdoc_types_order' => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'none'],
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try'],
        ],
    ])
    ->setFinder($finder);
