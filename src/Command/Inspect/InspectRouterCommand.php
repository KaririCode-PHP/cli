<?php

declare(strict_types=1);

namespace KaririCode\Cli\Command\Inspect;

use KaririCode\Cli\Enum\OutputFormat;
use KaririCode\Cli\Inspector\RouterInspector;
use KaririCode\Cli\Template\OutputRenderer;
use KaririCode\Cli\ValueObject\InspectionResult;
use KaririCode\Console\Attribute\AsCommand;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\ValueObject\OptionDescriptor;

/**
 * Lists all registered routes with method, path, handler, and name.
 *
 * ARFA 1.3: P4. Spec V4.3.3: Console command, final and stateless.
 *
 * Preconditions: bind a RouteSource for real data (ADR-011).
 * Thread-safety: stateless between dispatches.
 * Side effects: writes to Output.
 *
 * Example:
 *   kcli inspect:router --method=GET --format=tree
 *
 * References: ADR-011.
 * @see https://www.php-fig.org/psr/psr-15/
 */
#[AsCommand(name: 'inspect:router', description: 'List all registered routes')]
final class InspectRouterCommand implements Command
{
    public function __construct(
        private readonly RouterInspector $inspector,
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
            name: 'method',
            description: 'Filter by HTTP method (GET, POST, …)',
            default: '',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'path',
            description: 'Filter by path fragment',
            default: '',
        ));
    }

    public function execute(Input $input, Output $output): int
    {
        $format     = OutputFormat::fromString((string) ($input->getOption('format') ?? 'table'));
        $methodFilter = strtoupper((string) ($input->getOption('method') ?? ''));
        $pathFilter   = strtolower((string) ($input->getOption('path') ?? ''));

        $result = $this->inspector->inspect();

        $rows = $result->rows;

        if ($methodFilter !== '') {
            $rows = array_values(array_filter($rows, fn($r) => ($r['method'] ?? '') === $methodFilter));
        }

        if ($pathFilter !== '') {
            $rows = array_values(array_filter($rows, fn($r) => str_contains(strtolower((string) ($r['path'] ?? '')), $pathFilter)));
        }

        $filtered = new InspectionResult(
            componentName: $result->componentName,
            rows: $rows,
            summary: array_merge($result->summary, ['showing' => count($rows)]),
        );

        $this->renderer->render($filtered, $output, $format);

        return 0;
    }
}
