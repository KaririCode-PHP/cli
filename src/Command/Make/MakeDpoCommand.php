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
 * Generates a KaririCode DPO (Data Preparation Object).
 *
 * The DPO is KaririCode's answer to Laravel's FormRequest + DTO merged
 * into a single final readonly class. Fields inferred from their names
 * automatically receive validators and sanitizers — zero manual setup.
 *
 * Example:
 *   kcli make:dpo UserRegistration --fields=email:string,password:string,name:string
 *   kcli make:dpo UserRegistration --fields=email,password,name --dry-run
 *   kcli make:dpo UserRegistration --namespace=App\\Domain\\Auth --force
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
 * References: ADR-008.
 * @see https://www.php-fig.org/psr/psr-4/
 */
#[AsCommand(name: 'make:dpo', description: 'Generate a Data Preparation Object (DPO) with inferred validators and sanitizers')]
final class MakeDpoCommand implements Command
{
    public string $name {
        get => 'make:dpo';
    }

    public string $description {
        get => 'Generate a Data Preparation Object (DPO) with inferred validators and sanitizers';
    }

    public function __construct(
        private readonly GeneratorRegistry $generators,
        private readonly FieldInferenceEngine $inference,
    ) {}

    // ── Command contract ─────────────────────────────────────────────────

    #[\Override]
    public function configure(InputDefinition $definition): void
    {
        $definition->addArgument(new ArgumentDescriptor(
            name: 'name',
            description: 'Class name of the DPO (e.g. UserRegistration)',
            required: true,
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'fields',
            description: 'Comma-separated field list: name:type,... (e.g. email:string,age:int)',
            default: '',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'namespace',
            description: 'PHP namespace for the generated class',
            shortcut: 'N',
            default: 'App\\DataObject',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'path',
            description: 'Output directory (relative to cwd)',
            shortcut: 'p',
            default: 'src/DataObject',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'dry-run',
            description: 'Print the generated file without writing to disk',
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

    #[\Override]
    public function execute(Input $input, Output $output): ExitCode
    {
        try {
            $context = new GeneratorContext(
                className: ScaffoldRunner::className(InputValue::argument($input, 'name'), 'Dpo'),
                namespace: InputValue::text($input, 'namespace', 'App\\DataObject'),
                target: GeneratorTarget::Dpo,
                outputPath: ScaffoldRunner::outputPath(InputValue::text($input, 'path', 'src/DataObject')),
                fields: $this->inference->parseAll(InputValue::text($input, 'fields', '')),
                dryRun: InputValue::flag($input, 'dry-run'),
                force: InputValue::flag($input, 'force'),
            );

            (new ScaffoldRunner($this->generators))->run($context, $output);

            if (!$context->dryRun && $context->hasFields()) {
                $inferred = count(array_filter($context->fields, fn($f) => $f->hasProcessors()));
                $output->writeln(
                    "  <comment>{$inferred}/" . count($context->fields)
                    . ' fields received auto-inferred processors</comment>',
                );
            }
        } catch (ExceptionInterface $e) {
            $output->writeln("<error>✗ {$e->getMessage()}</error>");

            return ExitCode::Failure;
        }

        return ExitCode::Success;
    }
}
