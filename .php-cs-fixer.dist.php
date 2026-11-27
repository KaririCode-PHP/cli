<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/tools']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/build/.php-cs-fixer.cache')
    ->setRules([
        '@PER-CS3x0'             => true,
        'declare_strict_types'   => true,
        'no_unused_imports'      => true,
        'ordered_imports'        => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'binary_operator_spaces' => ['operators' => ['=>' => 'align_single_space_minimal', '=' => 'align_single_space_minimal']],
    ])
    ->setFinder($finder);
