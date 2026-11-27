<?php

declare(strict_types=1);

namespace KaririCode\Cli\Command\Inspect;

use KaririCode\Cli\Contract\DiagnosticsSource;
use KaririCode\Cli\Enum\OutputFormat;
use KaririCode\Cli\Template\OutputRenderer;
use KaririCode\Console\Attribute\AsCommand;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;
use KaririCode\Contract\Exception\ExceptionInterface;

/**
 * Runs source diagnostics through a DiagnosticsSource adapter.
 *
 * ARFA 1.3: P4. Spec V4.3.3: Console command, final and stateless; the
 * parser is reached through a CLI-owned port (ADR-011).
 *
 * Preconditions: a DiagnosticsSource is registered; otherwise exit code 1.
 * Thread-safety: stateless between dispatches.
 * Side effects: the adapter reads sources; writes to Output. Exit 1 = error
 * diagnostics or no adapter, 2 = invalid path or severity.
 *
 * Example:
 *   kcli inspect:diagnostics src --severity=warning --format=json
 *
 * References: ADR-011.
 * @see https://www.php.net/manual/en/function.token-get-all.php
 */
#[AsCommand(name: 'inspect:diagnostics', description: 'Run source diagnostics through a DiagnosticsSource adapter')]
final class InspectDiagnosticsCommand implements Command
{
    /** @var list<string> */
    private const array SEVERITIES = ['error', 'warning', 'info', 'hint'];

    public function __construct(
        private readonly OutputRenderer $renderer,
        private readonly ?DiagnosticsSource $source = null,
    ) {}

    public function configure(InputDefinition $definition): void
    {
        $definition->addArgument(new ArgumentDescriptor(
            name: 'path',
            description: 'File or directory to analyse',
            required: false,
            default: 'src',
        ));
        $definition->addOption(new OptionDescriptor(name: 'severity', description: 'Minimum severity: error, warning, info or hint', default: 'info'));
        $definition->addOption(new OptionDescriptor(name: 'format', description: 'Output format: human or json', default: 'human'));
        $definition->addOption(new OptionDescriptor(name: 'max-files', description: 'Stop after N files (0 = no limit)', default: '0'));
    }

    public function execute(Input $input, Output $output): int
    {
        if ($this->source === null) {
            $output->writeln('<error>✗ inspect:diagnostics needs a DiagnosticsSource adapter; none is registered (ADR-011).</error>');

            return 1;
        }

        $path     = (string) ($input->getArgument('path') ?? 'src');
        $severity = strtolower((string) ($input->getOption('severity') ?? 'info'));

        if (!file_exists($path) || !in_array($severity, self::SEVERITIES, true)) {
            $output->writeln('<error>✗ The path does not exist or the severity is not error, warning, info or hint.</error>');

            return 2;
        }

        try {
            $result = $this->source->diagnose(
                path: $path,
                minimumSeverity: $severity,
                maxFiles: max(0, (int) ($input->getOption('max-files') ?? 0)),
            );
        } catch (ExceptionInterface $e) {
            $output->writeln("<error>✗ {$e->getMessage()}</error>");

            return 1;
        }

        $json = (string) ($input->getOption('format') ?? 'human') === 'json';
        $this->renderer->render($result, $output, $json ? OutputFormat::Json : OutputFormat::Table);

        return (int) ($result->summary['errors'] ?? 0) > 0 ? 1 : 0;
    }
}
