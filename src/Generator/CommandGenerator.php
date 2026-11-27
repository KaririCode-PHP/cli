<?php

declare(strict_types=1);

namespace KaririCode\Cli\Generator;

use KaririCode\Cli\Contract\Generator;
use KaririCode\Cli\ValueObject\GeneratedFile;
use KaririCode\Cli\ValueObject\GeneratorContext;

/**
 * Generates a Console V4.1 interface-based command class.
 *
 * The generated command implements KaririCode\Console\Contract\Command
 * directly — no inheritance required (ARFA 1.3 P4). All input is
 * declared via #[Argument] / #[Option] attributes on a companion
 * readonly Input DTO, exploiting the Console V4.1 InputMapper.
 *
 * This is architecturally superior to Laravel's `make:command` which
 * forces extension of Illuminate\Console\Command.
 *
 */
final class CommandGenerator implements Generator
{
    public string $type {
        get => 'Command';
    }

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
        $ns        = $context->namespace;
        $class     = $context->className;
        $name      = $this->toCommandName($class);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns};

        use KaririCode\Console\Attribute\AsCommand;
        use KaririCode\Console\Contract\Command;
        use KaririCode\Console\Contract\Input;
        use KaririCode\Console\Contract\InputDefinition;
        use KaririCode\Console\Contract\Output;

        /**
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
            public function configure(InputDefinition \$definition): void
            {
                // TODO: add arguments / options
                // \$definition->addArgument(new ArgumentDescriptor(name: 'target', description: '...'));
                // \$definition->addOption(new OptionDescriptor(name: 'verbose', acceptsValue: false));
            }

            public function execute(Input \$input, Output \$output): int
            {
                // TODO: implement command logic
                \$output->writeln('<info>Done.</info>');

                return 0;
            }
        }
        PHP;
    }

    private function toCommandName(string $className): string
    {
        // UserCreateCommand → user:create
        $name = preg_replace('/Command\z/', '', $className) ?? $className;
        $snake = (string) preg_replace('/([A-Z])/', ':$1', lcfirst($name));
        return strtolower(ltrim($snake, ':'));
    }
}
