<?php

declare(strict_types=1);

namespace KaririCode\Cli\Command\Make;

use KaririCode\Cli\Application\InputValue;
use KaririCode\Cli\Application\ScaffoldRunner;
use KaririCode\Cli\Enum\GeneratorTarget;
use KaririCode\Cli\Generator\GeneratorRegistry;
use KaririCode\Cli\ValueObject\GeneratorContext;
use KaririCode\Console\Attribute\AsCommand;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\Enum\ExitCode;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;
use KaririCode\Contract\Exception\ExceptionInterface;

/**
 * Generates a Console 4.6 interface-based Command class.
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
 *   kcli make:command SendWelcomeEmail --dry-run
 *
 * References: ADR-008.
 * @see https://www.php-fig.org/psr/psr-4/
 */
#[AsCommand(name: 'make:command', description: 'Generate a KaririCode Console 4.6 interface-based command')]
final class MakeCommandCommand implements Command
{
    public string $name {
        get => 'make:command';
    }

    public string $description {
        get => 'Generate a KaririCode Console 4.6 interface-based command';
    }

    public function __construct(
        private readonly GeneratorRegistry $generators,
    ) {}

    #[\Override]
    public function configure(InputDefinition $definition): void
    {
        $definition->addArgument(new ArgumentDescriptor(
            name: 'name',
            description: 'Class name (e.g. SendWelcomeEmail)',
            required: true,
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'namespace',
            description: 'PHP namespace',
            shortcut: 'N',
            default: 'App\\Command',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'path',
            description: 'Output directory',
            shortcut: 'p',
            default: 'src/Command',
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

    #[\Override]
    public function execute(Input $input, Output $output): ExitCode
    {
        try {
            $context = new GeneratorContext(
                className: ScaffoldRunner::className(InputValue::argument($input, 'name'), 'Command'),
                namespace: InputValue::text($input, 'namespace', 'App\\Command'),
                target: GeneratorTarget::Command,
                outputPath: ScaffoldRunner::outputPath(InputValue::text($input, 'path', 'src/Command')),
                dryRun: InputValue::flag($input, 'dry-run'),
                force: InputValue::flag($input, 'force'),
            );

            (new ScaffoldRunner($this->generators))->run($context, $output);
        } catch (ExceptionInterface $e) {
            $output->writeln("<error>✗ {$e->getMessage()}</error>");

            return ExitCode::Failure;
        }

        return ExitCode::Success;
    }
}
