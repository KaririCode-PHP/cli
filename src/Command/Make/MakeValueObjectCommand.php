<?php

declare(strict_types=1);

namespace KaririCode\Cli\Command\Make;

use KaririCode\Cli\Application\InputValue;
use KaririCode\Cli\Application\ScaffoldRunner;
use KaririCode\Cli\Enum\GeneratorTarget;
use KaririCode\Cli\Generator\GeneratorRegistry;
use KaririCode\Cli\Inference\FieldInferenceEngine;
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
 * Generates a final readonly Value Object.
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
 *   kcli make:value-object Money --fields=amount:float,currency:string
 *
 * References: ADR-008.
 * @see https://www.php-fig.org/psr/psr-4/
 */
#[AsCommand(name: 'make:value-object', description: 'Generate a final readonly Value Object with self-validation factory')]
final class MakeValueObjectCommand implements Command
{
    public string $name {
        get => 'make:value-object';
    }

    public string $description {
        get => 'Generate a final readonly Value Object with self-validation factory';
    }

    public function __construct(
        private readonly GeneratorRegistry $generators,
        private readonly FieldInferenceEngine $inference,
    ) {}

    #[\Override]
    public function configure(InputDefinition $definition): void
    {
        $definition->addArgument(new ArgumentDescriptor(
            name: 'name',
            description: 'Value object class name (e.g. EmailAddress, Money)',
            required: true,
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'fields',
            description: 'Comma-separated fields: name:type,...',
            default: '',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'namespace',
            shortcut: 'N',
            description: 'PHP namespace',
            default: 'App\\ValueObject',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'path',
            shortcut: 'p',
            description: 'Output directory',
            default: 'src/ValueObject',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'dry-run',
            description: 'Preview without writing',
            acceptsValue: false,
            default: false,
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'force',
            shortcut: 'f',
            description: 'Overwrite if exists',
            acceptsValue: false,
            default: false,
        ));
    }

    #[\Override]
    public function execute(Input $input, Output $output): ExitCode
    {
        try {
            $context = new GeneratorContext(
                className: ScaffoldRunner::className(InputValue::argument($input, 'name'), ''),
                namespace: InputValue::text($input, 'namespace', 'App\\ValueObject'),
                target: GeneratorTarget::ValueObject,
                outputPath: ScaffoldRunner::outputPath(InputValue::text($input, 'path', 'src/ValueObject')),
                fields: $this->inference->parseAll(InputValue::text($input, 'fields', '')),
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
