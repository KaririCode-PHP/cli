<?php

declare(strict_types=1);

namespace KaririCode\Cli\Command\Inspect;

use KaririCode\Cli\Contract\SyntaxTreeDumper;
use KaririCode\Console\Attribute\AsCommand;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;
use KaririCode\Contract\Exception\ExceptionInterface;

/**
 * Dumps the syntax tree of one PHP file through a SyntaxTreeDumper adapter.
 *
 * ARFA 1.3: P4. Spec V4.3.3: Console command, final and stateless. The
 * parser is reached through a CLI-owned port, never by class name (ADR-011).
 *
 * Preconditions: a SyntaxTreeDumper is registered; otherwise exit code 1.
 * Thread-safety: stateless between dispatches.
 * Side effects: reads the file; writes to Output. Exit 2 = unreadable file.
 *
 * Example:
 *   kcli inspect:ast src/User.php --show-positions
 *
 * References: ADR-011.
 * @see https://www.php.net/manual/en/function.token-get-all.php
 */
#[AsCommand(name: 'inspect:ast', description: 'Dump the syntax tree of a PHP file through a SyntaxTreeDumper adapter')]
final class InspectAstCommand implements Command
{
    public function __construct(
        private readonly ?SyntaxTreeDumper $dumper = null,
    ) {}

    public function configure(InputDefinition $definition): void
    {
        $definition->addArgument(new ArgumentDescriptor(name: 'file', description: 'PHP file to parse', required: true));
        $definition->addOption(new OptionDescriptor(name: 'format', description: 'Output format: tree or json', default: 'tree'));
        $definition->addOption(new OptionDescriptor(
            name: 'show-positions',
            description: 'Include line and column of each node',
            acceptsValue: false,
            default: false,
        ));
    }

    public function execute(Input $input, Output $output): int
    {
        if ($this->dumper === null) {
            $output->writeln('<error>✗ inspect:ast needs a SyntaxTreeDumper adapter; none is registered (ADR-011).</error>');

            return 1;
        }

        $file = (string) $input->getArgument('file');

        if (!is_file($file) || !is_readable($file)) {
            $output->writeln('<error>✗ The file does not exist or is not readable.</error>');

            return 2;
        }

        try {
            $output->writeln($this->dumper->dump(
                file: $file,
                format: (string) ($input->getOption('format') ?? 'tree'),
                withPositions: (bool) $input->getOption('show-positions'),
            ));
        } catch (ExceptionInterface $e) {
            $output->writeln("<error>✗ {$e->getMessage()}</error>");

            return 1;
        }

        return 0;
    }
}
