<?php

declare(strict_types=1);

namespace KaririCode\Cli\Generator;

use KaririCode\Cli\Contract\Generator;
use KaririCode\Cli\ValueObject\GeneratedFile;
use KaririCode\Cli\ValueObject\GeneratorContext;

/**
 * Generates a KaririCode\Parser Grammar extension class.
 *
 * A Grammar defines a DSL rule set that integrates into the Parser V4.0
 * GrammarRegistry. The scaffold provides the three mandatory hook points:
 *   - rules(): registers TokenKind extensions and parse rules
 *   - keywords(): additional reserved words for the lexer
 *   - operators(): custom operator tokens with precedence
 *
 * Generated class targets KaririCode\Parser\Contract\Grammar.
 *
 */
final class GrammarGenerator implements Generator
{
    public string $type {
        get => 'Grammar';
    }

    public function generate(GeneratorContext $context): array
    {
        $namespace = $context->namespace;
        $class     = $context->className;

        $content = <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use KaririCode\\Parser\\Contract\\Grammar;
        use KaririCode\\Parser\\Registry\\GrammarRegistry;

        /**
         * Custom grammar extension for the KaririCode\\Parser.
         *
         * Register this grammar in the GrammarRegistry at bootstrap:
         *
         *   \$registry = new GrammarRegistry();
         *   \$registry->register(new {$class}());
         *
         * ARFA 1.3: P4. Spec V4.3.3: grammar extension for kariricode/parser.
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
            public function name(): string
            {
                return '{$class}';
            }

            /**
             * Registers parse rules into the grammar registry.
             *
             * Called once during parser bootstrap. Add token rules,
             * precedence tables, and production rules here.
             */
            public function rules(GrammarRegistry \$registry): void
            {
                // Example: \$registry->addRule('custom_stmt', fn(\$parser) => ...);
            }

            /**
             * Additional keywords that the lexer must recognize.
             *
             * Return an array of lowercase keyword strings.
             *
             * @return list<string>
             */
            public function keywords(): array
            {
                return [];
            }

            /**
             * Custom operator tokens with their precedence.
             *
             * Keys are token strings; values are integer precedence levels
             * (higher = tighter binding, same scale as built-in operators).
             *
             * @return array<string, int>
             */
            public function operators(): array
            {
                return [];
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
}
