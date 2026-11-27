<?php

declare(strict_types=1);

namespace KaririCode\Cli\Generator;

use KaririCode\Cli\Contract\Generator;
use KaririCode\Cli\ValueObject\GeneratedFile;
use KaririCode\Cli\ValueObject\GeneratorContext;

/**
 * Generates a Console 4.6 interface-based command class.
 *
 * The generated command implements KaririCode\Console\Contract\Command
 * directly, with no base class (ARFA 1.3 P4): name and description as
 * interface properties, configure() for its arguments and options, and an
 * execute() that returns ExitCode.
 *
 * ARFA 1.3: P1 (the output depends only on the GeneratorContext).
 * Spec V4.3.3: stateless generator, final class.
 *
 * Preconditions: the context passed GeneratorContext validation.
 * Thread-safety: stateless; safe to share between fibers.
 * Side effects: none; FileWriter writes the returned files (ADR-005).
 *
 * Example:
 *   $files = (new CommandGenerator())->generate($context);
 *
 * References: ADR-001 (amendment of 2026-11-27), ADR-012.
 * @see https://www.php.net/manual/en/language.oop5.property-hooks.php
 */
final class CommandGenerator implements Generator
{
    public string $type {
        get => 'Command';
    }

    #[\Override]
    public function generate(GeneratorContext $context): array
    {
        return [
            new GeneratedFile(
                path: $context->targetFilePath(),
                content: $this->renderCommand($context),
                label: "Command: {$context->fullyQualifiedClassName()}",
            ),
        ];
    }

    private function renderCommand(GeneratorContext $context): string
    {
        $ns    = $context->namespace;
        $class = $context->className;
        $name  = $this->toCommandName($class);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns};

        use KaririCode\Console\Attribute\AsCommand;
        use KaririCode\Console\Contract\Command;
        use KaririCode\Console\Contract\Input;
        use KaririCode\Console\Contract\InputDefinition;
        use KaririCode\Console\Contract\Output;
        use KaririCode\Console\Enum\ExitCode;

        /**
         * Console command {$name}. TODO: describe its role.
         *
         * ARFA 1.3: P4 (protocol agnosticism). Spec V4.3.3: Console command,
         * final and stateless.
         *
         * Preconditions: registered in a kariricode/console Application.
         * Thread-safety: stateless between dispatches.
         * Side effects: writes to Output.
         *
         * Example:
         *   \$application->add(new {$class}());
         *
         * References:
         * @see https://www.php.net/manual/en/language.attributes.php
         */
        #[AsCommand(name: '{$name}', description: 'TODO: describe this command')]
        final class {$class} implements Command
        {
            public string \$name {
                get => '{$name}';
            }

            public string \$description {
                get => 'TODO: describe this command';
            }

            #[\Override]
            public function configure(InputDefinition \$definition): void
            {
                // TODO: add arguments / options
                // \$definition->addArgument(new ArgumentDescriptor(name: 'target', description: '...'));
                // \$definition->addOption(new OptionDescriptor(name: 'verbose', acceptsValue: false));
            }

            #[\Override]
            public function execute(Input \$input, Output \$output): ExitCode
            {
                // TODO: implement command logic
                \$output->writeln('<info>Done.</info>');

                return ExitCode::Success;
            }
        }
        PHP;
    }

    private function toCommandName(string $className): string
    {
        // UserCreateCommand → user:create
        $name  = preg_replace('/Command\z/', '', $className) ?? $className;
        $snake = (string) preg_replace('/([A-Z])/', ':$1', lcfirst($name));
        return strtolower(ltrim($snake, ':'));
    }
}
