<?php

declare(strict_types=1);

namespace KaririCode\Cli\Generator;

use KaririCode\Cli\Contract\Generator;
use KaririCode\Cli\ValueObject\GeneratedFile;
use KaririCode\Cli\ValueObject\GeneratorContext;

/**
 * Generates a domain Service class with DI constructor injection.
 *
 */
final class ServiceGenerator implements Generator
{
    public string $type {
        get => 'Service';
    }

    public function generate(GeneratorContext $context): array
    {
        return [
            new GeneratedFile(
                path: $context->targetFilePath(),
                content: $this->render($context),
                label: "Service: {$context->fullyQualifiedClassName()}",
            ),
        ];
    }

    private function render(GeneratorContext $context): string
    {
        $ns     = $context->namespace;
        $class  = $context->className;

        // Infer a repository hint from class name: UserService → UserRepository
        $domainName = preg_replace('/Service\z/', '', $class) ?? $class;
        $repoHint   = $domainName . 'Repository';

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns};

        /**
         * Application service: {$class}.
         *
         * ARFA 1.3: P4. Spec V4.3.3: application service, final class.
         *
         * Preconditions: dependencies arrive through the constructor.
         * Thread-safety: stateless; safe to share.
         * Side effects: delegates persistence to its repository.
         *
         * Example:
         *   \$service = new {$class}(\$repository);
         *
         * References:
         * @see https://www.php-fig.org/psr/psr-11/
         */
        final class {$class}
        {
            public function __construct(
                // TODO: inject repository / dependencies
                // private readonly {$repoHint} \$repository,
            ) {}
        }
        PHP;
    }
}
