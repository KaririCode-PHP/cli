<?php

declare(strict_types=1);

namespace KaririCode\Cli\Command\Make;

use KaririCode\Cli\Application\ScaffoldRunner;
use KaririCode\Cli\Enum\GeneratorTarget;
use KaririCode\Cli\Generator\GeneratorRegistry;
use KaririCode\Cli\ValueObject\GeneratorContext;
use KaririCode\Console\Attribute\AsCommand;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;
use KaririCode\Contract\Exception\ExceptionInterface;

/**
 * Scaffolds a KaririCode\Parser NodeVisitor implementation.
 *
 * ARFA 1.3: P4. Spec V4.3.3: Console command, final and stateless; delegates to
 * ScaffoldRunner (ADR-008).
 *
 * Preconditions: the working directory is the project root; GeneratorContext validates
 * names and namespaces.
 * Thread-safety: stateless between dispatches.
 * Side effects: writes files through FileWriter unless --dry-run, and lines to
 * Output; exit 1 on any kariricode/contract exception.
 *
 * Example:
 *   kcli make:visitor ImportCollector
 *
 * References: ADR-008.
 * @see https://www.php-fig.org/psr/psr-4/
 */
#[AsCommand(name: 'make:visitor', description: 'Scaffold a Parser NodeVisitor implementation')]
final class MakeVisitorCommand implements Command
{
    public function __construct(
        private readonly GeneratorRegistry $registry,
    ) {}

    public function configure(InputDefinition $definition): void
    {
        $definition->addArgument(new ArgumentDescriptor(
            name: 'name',
            description: 'Visitor class name (e.g. ImportCollector)',
            required: true,
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'namespace',
            description: 'Target namespace',
            shortcut: 'N',
            default: 'App\\Parser\\Visitor',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'path',
            description: 'Output directory',
            shortcut: 'p',
            default: 'src/Parser/Visitor',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'dry-run',
            description: 'Preview without writing files',
            acceptsValue: false,
            default: false,
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'force',
            description: 'Overwrite existing files',
            shortcut: 'f',
            acceptsValue: false,
            default: false,
        ));
    }

    public function execute(Input $input, Output $output): int
    {
        try {
            $context = new GeneratorContext(
                className: ScaffoldRunner::className((string) $input->getArgument('name'), 'Visitor'),
                namespace: (string) ($input->getOption('namespace') ?? 'App\\Parser\\Visitor'),
                target: GeneratorTarget::Visitor,
                outputPath: ScaffoldRunner::outputPath((string) ($input->getOption('path') ?? 'src/Parser/Visitor')),
                dryRun: (bool) $input->getOption('dry-run'),
                force: (bool) $input->getOption('force'),
            );

            (new ScaffoldRunner($this->registry))->run($context, $output);
        } catch (ExceptionInterface $e) {
            $output->writeln("<error>✗ {$e->getMessage()}</error>");

            return 1;
        }

        return 0;
    }
}
