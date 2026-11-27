<?php

declare(strict_types=1);

namespace KaririCode\Cli\Command\Inspect;

use KaririCode\Cli\Enum\OutputFormat;
use KaririCode\Cli\Inspector\ContainerInspector;
use KaririCode\Cli\Template\OutputRenderer;
use KaririCode\Cli\ValueObject\InspectionResult;
use KaririCode\Console\Attribute\AsCommand;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\ValueObject\OptionDescriptor;

/**
 * Displays all DI container bindings in the current application.
 *
 * ARFA 1.3: P4. Spec V4.3.3: Console command, final and stateless.
 *
 * Preconditions: bind a BindingSource for real data (ADR-011).
 * Thread-safety: stateless between dispatches.
 * Side effects: writes to Output.
 *
 * Example:
 *   kcli inspect:container --filter=Logger
 *
 * References: ADR-011.
 * @see https://www.php-fig.org/psr/psr-11/
 */
#[AsCommand(name: 'inspect:container', description: 'Inspect DI container bindings')]
final class InspectContainerCommand implements Command
{
    public function __construct(
        private readonly ContainerInspector $inspector,
        private readonly OutputRenderer $renderer,
    ) {}

    public function configure(InputDefinition $definition): void
    {
        $definition->addOption(new OptionDescriptor(
            name: 'format',
            description: 'Output format: table, tree, json, plain',
            default: 'table',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'filter',
            description: 'Filter bindings by partial abstract name',
            default: '',
        ));
    }

    public function execute(Input $input, Output $output): int
    {
        $format = OutputFormat::fromString((string) ($input->getOption('format') ?? 'table'));
        $filter = strtolower((string) ($input->getOption('filter') ?? ''));

        $result = $this->inspector->inspect();

        // Apply filter
        if ($filter !== '') {
            $filtered = array_values(array_filter(
                $result->rows,
                fn($row) => str_contains(strtolower((string) ($row['abstract'] ?? '')), $filter),
            ));

            $result = new InspectionResult(
                componentName: $result->componentName,
                rows: $filtered,
                summary: array_merge($result->summary, ['filtered' => count($filtered)]),
            );
        }

        $this->renderer->render($result, $output, $format);

        return 0;
    }
}
