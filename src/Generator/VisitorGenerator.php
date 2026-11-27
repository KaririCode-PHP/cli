<?php

declare(strict_types=1);

namespace KaririCode\Cli\Generator;

use KaririCode\Cli\Contract\Generator;
use KaririCode\Cli\ValueObject\GeneratedFile;
use KaririCode\Cli\ValueObject\GeneratorContext;

/**
 * Generates a KaririCode\Parser NodeVisitor implementation.
 *
 * A Visitor traverses the AST produced by the Parser and collects or
 * transforms nodes without modifying the tree (Visitor pattern — GoF §331).
 * Implements KaririCode\Parser\Contract\NodeVisitor.
 *
 * The scaffold includes the enterNode / leaveNode pair and a typed
 * result() accessor — the canonical pattern used by all built-in
 * visitors (ASTDumper, SymbolCollector, DiagnosticEmitter).
 *
 */
final class VisitorGenerator implements Generator
{
    public string $type {
        get => 'Visitor';
    }

    public function generate(GeneratorContext $context): array
    {
        $namespace = $context->namespace;
        $class     = $context->className;

        $content = <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use KaririCode\\Parser\\AST\\Node;
        use KaririCode\\Parser\\Contract\\NodeVisitor;

        /**
         * AST visitor: {$class}.
         *
         * Traverses the parse tree and collects or transforms nodes.
         * Register via \$parser->addVisitor(new {$class}()) before
         * calling parse() so the visitor receives every node in
         * source order.
         *
         * ARFA 1.3: P4. Spec V4.3.3: AST visitor for kariricode/parser.
         *
         * Preconditions: used for one traversal at a time.
         * Thread-safety: holds traversal state; one instance per traversal.
         * Side effects: accumulates results in memory.
         *
         * Example:
         *   \$visitor = new {$class}();
         *
         * References:
         * @see https://en.wikipedia.org/wiki/Visitor_pattern
         */
        final class {$class} implements NodeVisitor
        {
            /** @var list<mixed> Accumulated results during traversal. */
            private array \$results = [];

            /**
             * Called on entering each AST node (pre-order).
             *
             * Return false to skip descending into this node's children.
             */
            public function enterNode(Node \$node): bool
            {
                // TODO: match on node type and collect/transform
                return true;
            }

            /**
             * Called on leaving each AST node (post-order).
             */
            public function leaveNode(Node \$node): void
            {
                // TODO: finalize results after children are visited
            }

            /**
             * Returns accumulated results after full traversal.
             *
             * @return list<mixed>
             */
            public function results(): array
            {
                return \$this->results;
            }

            /**
             * Resets accumulated state so the visitor can be reused
             * across multiple parse runs.
             */
            public function reset(): void
            {
                \$this->results = [];
            }
        }
        PHP;

        return [
            new GeneratedFile(
                path: $context->targetFilePath(),
                content: $content,
                label: "Visitor: {$class}",
            ),
        ];
    }
}
