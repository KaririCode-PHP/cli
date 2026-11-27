<?php

declare(strict_types=1);

namespace KaririCode\Cli\Generator;

use KaririCode\Cli\Contract\Generator;
use KaririCode\Cli\ValueObject\GeneratedFile;
use KaririCode\Cli\ValueObject\GeneratorContext;

/**
 * Generates a NodeVisitor for kariricode/parser.
 *
 * The generated class implements KaririCode\Parser\Contract\NodeVisitor
 * as kariricode/parser 4.1 declares it: NodeTraverser calls enterNode()
 * before a node's children and leaveNode() after them, and a returned
 * Node replaces the visited one, while null keeps it (Visitor pattern).
 *
 * ARFA 1.3: P1 (the output depends only on the GeneratorContext).
 * Spec V4.3.3: stateless generator, final class.
 *
 * Preconditions: the context passed GeneratorContext validation.
 * Thread-safety: stateless; safe to share between fibers.
 * Side effects: none; FileWriter writes the returned files (ADR-005).
 *
 * Example:
 *   $files = (new VisitorGenerator())->generate($context);
 *
 * References: ADR-011 (kariricode/parser stays optional), ADR-012.
 * @see https://en.wikipedia.org/wiki/Visitor_pattern
 */
final class VisitorGenerator implements Generator
{
    public string $type {
        get => 'Visitor';
    }

    #[\Override]
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
         * Walks the tree that kariricode/parser produces. NodeTraverser calls
         * enterNode() before a node's children and leaveNode() after them; a
         * returned Node replaces the visited one, null keeps it.
         *
         * ARFA 1.3: P4. Spec V4.3.3: AST visitor for kariricode/parser.
         *
         * Preconditions: used for one traversal at a time.
         * Thread-safety: holds traversal state; one instance per traversal.
         * Side effects: collects nodes in memory.
         *
         * Example:
         *   (new NodeTraverser())->addVisitor(\$visitor = new {$class}())->traverse(\$ast);
         *
         * References:
         * @see https://en.wikipedia.org/wiki/Visitor_pattern
         */
        final class {$class} implements NodeVisitor
        {
            /** @var list<Node> Nodes collected during the traversal. */
            public private(set) array \$results = [];

            #[\Override]
            public function enterNode(Node \$node): ?Node
            {
                // TODO: match on the node type; collect it, or return a replacement
                return null;
            }

            #[\Override]
            public function leaveNode(Node \$node): ?Node
            {
                // TODO: finish the work that needs the node's children
                return null;
            }

            /** Clears the collected nodes so the instance can walk another tree. */
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
