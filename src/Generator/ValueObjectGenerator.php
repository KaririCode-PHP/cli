<?php

declare(strict_types=1);

namespace KaririCode\Cli\Generator;

use KaririCode\Cli\Contract\Generator;
use KaririCode\Cli\ValueObject\FieldDescriptor;
use KaririCode\Cli\ValueObject\GeneratedFile;
use KaririCode\Cli\ValueObject\GeneratorContext;

/**
 * Generates a final readonly Value Object with a self-validating factory.
 *
 * ARFA 1.3: P1 (the output depends only on the GeneratorContext).
 * Spec V4.3.3: stateless generator, final class.
 *
 * Preconditions: the context passed GeneratorContext validation.
 * Thread-safety: stateless; safe to share between fibers.
 * Side effects: none; FileWriter writes the returned files (ADR-005).
 *
 * Example:
 *   $files = (new ValueObjectGenerator())->generate($context);
 *
 * References: ADR-012.
 * @see https://martinfowler.com/bliki/ValueObject.html
 */
final class ValueObjectGenerator implements Generator
{
    /** Constructor-promoted parameter indent (class 0 → method 4 → param 8). */
    private const string PARAM_INDENT = '        ';

    /** Method-body statement indent (class 0 → method 4 → body 8). */
    private const string BODY_INDENT = '        ';

    public string $type {
        get => 'ValueObject';
    }

    #[\Override]
    public function generate(GeneratorContext $context): array
    {
        return [
            new GeneratedFile(
                path: $context->targetFilePath(),
                content: $this->render($context),
                label: "ValueObject: {$context->fullyQualifiedClassName()}",
            ),
        ];
    }

    private function render(GeneratorContext $context): string
    {
        $ns          = $context->namespace;
        $class       = $context->className;
        $props       = $this->buildProperties($context->fields);
        $factoryArgs = $this->buildFactoryArgs($context->fields);
        $guardBlock  = $this->buildGuards($context->fields);
        $ctorCall    = $this->buildConstructorCall($context->fields);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns};

        /**
         * Value Object: {$class}.
         *
         * Immutable, self-validating. Use the static factory for construction
         * so domain invariants are always checked before the object exists.
         *
         * ARFA 1.3: P1 (immutable state). Spec V4.3.3: Value Object, final readonly.
         *
         * Preconditions: build through of(); its guards enforce the invariants.
         * Thread-safety: immutable; safe to share between fibers.
         * Side effects: none.
         *
         * Example:
         *   \$value = {$class}::of(...);
         *
         * References:
         * @see https://www.php.net/manual/en/language.oop5.properties.php#language.oop5.properties.readonly-properties
         */
        final readonly class {$class}
        {
            private function __construct(
        {$props}
            ) {}

            // ── Factory ──────────────────────────────────────────────────────────

            public static function of({$factoryArgs}): self
            {
        {$guardBlock}
                return new self({$ctorCall});
            }

            // ── Equality ─────────────────────────────────────────────────────────

            public function equals(self \$other): bool
            {
                return \$this === \$other || \$this == \$other;
            }
        }
        PHP;
    }

    /** @param list<FieldDescriptor> $fields */
    private function buildProperties(array $fields): string
    {
        $indent = self::PARAM_INDENT;

        if ($fields === []) {
            return "{$indent}public string \$value,";
        }

        return implode("\n", array_map(
            fn(FieldDescriptor $f) => "{$indent}public {$f->phpType()} \${$f->camelName()},",
            $fields,
        ));
    }

    /** @param list<FieldDescriptor> $fields */
    private function buildFactoryArgs(array $fields): string
    {
        if ($fields === []) {
            return 'string $value';
        }

        return implode(', ', array_map(
            fn(FieldDescriptor $f) => "{$f->phpType()} \${$f->camelName()}",
            $fields,
        ));
    }

    /** @param list<FieldDescriptor> $fields */
    private function buildConstructorCall(array $fields): string
    {
        if ($fields === []) {
            return '$value';
        }

        return implode(', ', array_map(
            fn(FieldDescriptor $f) => "\${$f->camelName()}",
            $fields,
        ));
    }

    /** @param list<FieldDescriptor> $fields */
    private function buildGuards(array $fields): string
    {
        $indent = self::BODY_INDENT;
        $lines  = [];

        foreach ($fields as $field) {
            if (in_array('NotBlank', $field->validators, strict: true)) {
                $n       = $field->camelName();
                $lines[] = "{$indent}if (trim(\${$n}) === '') {\n"
                    . "{$indent}    throw new \\InvalidArgumentException('{$n} cannot be blank.');\n"
                    . "{$indent}}";
            }
        }

        return $lines !== []
            ? implode("\n", $lines)
            : "{$indent}// TODO: add domain invariant guards";
    }
}
