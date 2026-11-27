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
 * Generates an application Service class.
 *
 * ServiceGenerator was registered in GeneratorRegistry since 4.0.0 but had
 * no command, so the target was unreachable from the CLI (added in 4.1.0).
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
 *   kcli make:service UserRegistration
 *
 * References: ADR-008.
 * @see https://www.php-fig.org/psr/psr-4/
 */
#[AsCommand(name: 'make:service', description: 'Generate an application service class')]
final class MakeServiceCommand implements Command
{
    public function __construct(
        private readonly GeneratorRegistry $generators,
    ) {}

    public function configure(InputDefinition $definition): void
    {
        $definition->addArgument(new ArgumentDescriptor(
            name: 'name',
            description: 'Class name (e.g. UserRegistration → UserRegistrationService)',
            required: true,
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'namespace',
            description: 'PHP namespace',
            shortcut: 'N',
            default: 'App\\Service',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'path',
            description: 'Output directory',
            shortcut: 'p',
            default: 'src/Service',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'dry-run',
            description: 'Preview without writing',
            acceptsValue: false,
            default: false,
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'force',
            description: 'Overwrite if exists',
            shortcut: 'f',
            acceptsValue: false,
            default: false,
        ));
    }

    public function execute(Input $input, Output $output): int
    {
        try {
            $context = new GeneratorContext(
                className: ScaffoldRunner::className((string) $input->getArgument('name'), 'Service'),
                namespace: (string) ($input->getOption('namespace') ?? 'App\\Service'),
                target: GeneratorTarget::Service,
                outputPath: ScaffoldRunner::outputPath((string) ($input->getOption('path') ?? 'src/Service')),
                dryRun: (bool) $input->getOption('dry-run'),
                force: (bool) $input->getOption('force'),
            );

            (new ScaffoldRunner($this->generators))->run($context, $output);
        } catch (ExceptionInterface $e) {
            $output->writeln("<error>✗ {$e->getMessage()}</error>");

            return 1;
        }

        return 0;
    }
}
