<?php

declare(strict_types=1);

namespace KaririCode\Cli\Inspector;

use KaririCode\Cli\Contract\BindingSource;
use KaririCode\Cli\Contract\Inspector;
use KaririCode\Cli\ValueObject\InspectionResult;

/**
 * Lists container bindings through the BindingSource port.
 *
 * ARFA 1.3: P4. Spec V4.3.3: stateless service; replaces the duck-typed
 * `object` container of 4.0 (§31, ADR-011).
 *
 * Preconditions: none; without a source the result carries a status line.
 * Thread-safety: holds only the injected port.
 * Side effects: none beyond the port's own reads.
 *
 * Example:
 *   $result = (new ContainerInspector($bindingSource))->inspect();
 *
 * References: ADR-011.
 * @see https://www.php-fig.org/psr/psr-11/
 */
final class ContainerInspector implements Inspector
{
    public string $componentName {
        get => 'DI Container';
    }

    public function __construct(
        private readonly ?BindingSource $source = null,
    ) {}

    public function inspect(): InspectionResult
    {
        if ($this->source === null) {
            return new InspectionResult(
                componentName: $this->componentName,
                rows: [],
                summary: ['status' => 'No BindingSource registered (ADR-011)'],
            );
        }

        $rows = [];

        foreach ($this->source->bindings() as $binding) {
            $rows[] = [
                'abstract'  => $binding->abstract,
                'concrete'  => $binding->concrete,
                'singleton' => $binding->singleton ? 'yes' : 'no',
            ];
        }

        usort($rows, static fn(array $a, array $b): int => strcmp($a['abstract'], $b['abstract']));

        return new InspectionResult(
            componentName: $this->componentName,
            rows: $rows,
            summary: [
                'total_bindings' => count($rows),
                'singletons'     => count(array_filter($rows, static fn(array $row): bool => $row['singleton'] === 'yes')),
            ],
        );
    }
}
