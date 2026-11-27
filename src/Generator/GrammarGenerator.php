<?php

declare(strict_types=1);

namespace KaririCode\Cli\Generator;

use KaririCode\Cli\Contract\Generator;
use KaririCode\Cli\ValueObject\GeneratedFile;
use KaririCode\Cli\ValueObject\GeneratorContext;

/**
 * Generates a grammar for kariricode/parser.
 *
 * The generated class implements KaririCode\Parser\Contract\Grammar as
 * kariricode/parser 4.1 declares it: id(), the key GrammarRegistry stores
 * it under, and phpVersionRange(). The id is the class name without the
 * "Grammar" suffix, in kebab case (BladeGrammar → "blade").
 *
 * ARFA 1.3: P1 (the output depends only on the GeneratorContext).
 * Spec V4.3.3: stateless generator, final class.
 *
 * Preconditions: the context passed GeneratorContext validation.
 * Thread-safety: stateless; safe to share between fibers.
 * Side effects: none; FileWriter writes the returned files (ADR-005).
 *
 * Example:
 *   $files = (new GrammarGenerator())->generate($context);
 *
 * References: ADR-011 (kariricode/parser stays optional), ADR-012.
 * @see https://en.wikipedia.org/wiki/Formal_grammar
 */
final class GrammarGenerator implements Generator
{
    public string $type {
        get => 'Grammar';
    }

    #[\Override]
    public function generate(GeneratorContext $context): array
    {
        $namespace = $context->namespace;
        $class     = $context->className;
        $id        = self::grammarId($class);

        $content = <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use KaririCode\\Parser\\Contract\\Grammar;

        /**
         * Grammar: {$class}.
         *
         * Identifies a language or dialect to kariricode/parser.
         * GrammarRegistry stores grammars by id(), which must be unique among
         * the registered grammars.
         *
         * ARFA 1.3: P4. Spec V4.3.3: grammar for kariricode/parser, final and
         * stateless.
         *
         * Preconditions: registered in a GrammarRegistry before parsing.
         * Thread-safety: stateless; safe to share.
         * Side effects: none.
         *
         * Example:
         *   \$registry->register(new {$class}());
         *
         * References:
         * @see https://www.php.net/manual/en/language.oop5.interfaces.php
         */
        final class {$class} implements Grammar
        {
            #[\Override]
            public function id(): string
            {
                return '{$id}';
            }

            #[\Override]
            public function phpVersionRange(): string
            {
                // TODO: the PHP versions this grammar accepts, as "min-max"
                return '8.4-8.4';
            }
        }
        PHP;

        return [
            new GeneratedFile(
                path: $context->targetFilePath(),
                content: $content,
                label: "Grammar: {$class}",
            ),
        ];
    }

    /** BladeGrammar → "blade", TwigTemplateGrammar → "twig-template". */
    private static function grammarId(string $class): string
    {
        $base = preg_replace('/Grammar\z/', '', $class);
        $base = $base === null || $base === '' ? $class : $base;

        return strtolower((string) preg_replace('/(?<=[a-z0-9])[A-Z]/', '-$0', $base));
    }
}
