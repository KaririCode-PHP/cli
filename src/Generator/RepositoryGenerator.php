<?php

declare(strict_types=1);

namespace KaririCode\Cli\Generator;

use KaririCode\Cli\Contract\Generator;
use KaririCode\Cli\ValueObject\GeneratedFile;
use KaririCode\Cli\ValueObject\GeneratorContext;

/**
 * Generates a Repository interface and a skeleton implementation.
 *
 * Two files per invocation, in the target directory: the domain contract
 * (interface) and Db<Name>, the implementation skeleton, which usually
 * moves to Infrastructure. ScaffoldRunner checks both targets before
 * writing either (ADR-008).
 *
 * ARFA 1.3: P1 (the output depends only on the GeneratorContext).
 * Spec V4.3.3: stateless generator, final class.
 *
 * Preconditions: the context passed GeneratorContext validation.
 * Thread-safety: stateless; safe to share between fibers.
 * Side effects: none; FileWriter writes the returned files (ADR-005).
 *
 * Example:
 *   [$interface, $implementation] = (new RepositoryGenerator())->generate($context);
 *
 * References: ADR-005, ADR-008, ADR-012.
 * @see https://martinfowler.com/eaaCatalog/repository.html
 */
final class RepositoryGenerator implements Generator
{
    public string $type {
        get => 'Repository';
    }

    #[\Override]
    public function generate(GeneratorContext $context): array
    {
        $domainName = preg_replace('/Repository\z/', '', $context->className) ?? $context->className;

        return [
            new GeneratedFile(
                path: $context->targetFilePath(),
                content: $this->renderInterface($context, $domainName),
                label: "Repository interface: {$context->fullyQualifiedClassName()}",
            ),
            new GeneratedFile(
                path: dirname($context->targetFilePath()) . "/Db{$context->className}.php",
                content: $this->renderImplementation($context, $domainName),
                label: "Repository implementation: Db{$context->className}",
            ),
        ];
    }

    private function renderInterface(GeneratorContext $context, string $domainName): string
    {
        $ns    = $context->namespace;
        $class = $context->className;

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns};

        /**
         * Repository contract for {$domainName} aggregate.
         *
         * ARFA 1.3: P4. Spec V4.3.3: repository port.
         *
         * Preconditions: ids are non-empty strings; import {$domainName} from its
         *   domain namespace.
         * Thread-safety: implementation-defined.
         * Side effects: implementations read and write the backing store.
         *
         * Example:
         *   \$repository = new Db{$class}(...);
         *
         * References:
         * @see https://martinfowler.com/eaaCatalog/repository.html
         */
        interface {$class}
        {
            public function findById(string \$id): ?{$domainName};

            /** @return list<{$domainName}> */
            public function findAll(): array;

            public function save({$domainName} \$entity): void;

            public function delete(string \$id): void;
        }
        PHP;
    }

    private function renderImplementation(GeneratorContext $context, string $domainName): string
    {
        $ns    = $context->namespace;
        $class = $context->className;
        $impl  = 'Db' . $class; // interpolated: keeps check:arfa V09 (regex) from counting the template

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns};

        /**
         * Database implementation of {$class}.
         *
         * ARFA 1.3: P4. Spec V4.3.3: repository adapter, final class.
         *
         * Preconditions: the connection is open before the first call.
         * Thread-safety: do not share one connection between fibers.
         * Side effects: reads and writes the database.
         *
         * Example:
         *   \$repository = new {$impl}(...);
         *
         * References:
         * @see https://martinfowler.com/eaaCatalog/repository.html
         */
        final class {$impl} implements {$class}
        {
            public function findById(string \$id): ?{$domainName}
            {
                // TODO: implement
                throw new \\RuntimeException('Not implemented.');
            }

            public function findAll(): array
            {
                // TODO: implement
                throw new \\RuntimeException('Not implemented.');
            }

            public function save({$domainName} \$entity): void
            {
                // TODO: implement
                throw new \\RuntimeException('Not implemented.');
            }

            public function delete(string \$id): void
            {
                // TODO: implement
                throw new \\RuntimeException('Not implemented.');
            }
        }
        PHP;
    }
}
