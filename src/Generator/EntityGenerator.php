<?php

declare(strict_types=1);

namespace KaririCode\Cli\Generator;

use KaririCode\Cli\Contract\Generator;
use KaririCode\Cli\ValueObject\FieldDescriptor;
use KaririCode\Cli\ValueObject\GeneratedFile;
use KaririCode\Cli\ValueObject\GeneratorContext;

/**
 * Generates a domain Entity class (identity-bearing, internally mutable).
 *
 * Follows the Spec V4.3.3 type taxonomy: an Entity is a regular final class
 * whose identity is `public readonly` and whose state is `public
 * private(set)` (PHP 8.4 asymmetric visibility). State is readable from
 * outside and only changes through the entity's own methods, so no
 * Java-style getters are generated.
 *
 * ARFA 1.3: P1 (the output depends only on the GeneratorContext).
 * Spec V4.3.3: stateless generator, final class.
 *
 * Preconditions: the context passed GeneratorContext validation.
 * Thread-safety: stateless; safe to share between fibers.
 * Side effects: none; FileWriter writes the returned files (ADR-005).
 *
 * Example:
 *   $files = (new EntityGenerator())->generate($context);
 *
 * References: ADR-012.
 * @see https://www.php.net/manual/en/language.oop5.visibility.php#language.oop5.visibility-members-aviz
 */
final class EntityGenerator implements Generator
{
    /** Constructor-promoted parameter indent (class 0 → method 4 → param 8). */
    private const string PARAM_INDENT = '        ';

    public string $type {
        get => 'Entity';
    }

    #[\Override]
    public function generate(GeneratorContext $context): array
    {
        return [
            new GeneratedFile(
                path: $context->targetFilePath(),
                content: $this->render($context),
                label: "Entity: {$context->fullyQualifiedClassName()}",
            ),
        ];
    }

    private function render(GeneratorContext $context): string
    {
        $ns          = $context->namespace;
        $class       = $context->className;
        $ctorParams  = $this->buildConstructorParams($context->fields);
        $factorySig  = $this->buildFactorySig($context->fields);
        $factoryCall = $this->buildFactoryCall($context->fields);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$ns};

        /**
         * Domain entity: {$class}.
         *
         * Entities are identity-bearing objects — two instances with the same id
         * are considered equal regardless of their other properties. State is
         * publicly readable and privately writable (PHP 8.4 asymmetric
         * visibility); add domain methods that change it.
         *
         * ARFA 1.3: P1 (state changes only through the entity). Spec V4.3.3:
         * Entity, final class with public private(set) state.
         *
         * Preconditions: the id is unique within its repository.
         * Thread-safety: mutable; confine an instance to one fiber.
         * Side effects: domain methods change this instance only.
         *
         * Example:
         *   \$entity = {$class}::create(\$id);
         *
         * References:
         * @see https://www.php.net/manual/en/language.oop5.visibility.php
         */
        final class {$class}
        {
            private function __construct(
        {$ctorParams}
            ) {}

            // ── Factories ────────────────────────────────────────────────────────

            public static function create(string \$id{$factorySig}): self
            {
                return new self(\$id{$factoryCall});
            }

            // ── Identity ─────────────────────────────────────────────────────────

            public function equals(self \$other): bool
            {
                return \$this->id === \$other->id;
            }
        }
        PHP;
    }

    /** @param list<FieldDescriptor> $fields */
    private function buildConstructorParams(array $fields): string
    {
        $indent = self::PARAM_INDENT;
        $lines  = ["{$indent}public readonly string \$id,"];

        foreach ($fields as $field) {
            $lines[] = "{$indent}public private(set) {$field->phpType()} \${$field->camelName()},";
        }

        return implode("\n", $lines);
    }

    /** @param list<FieldDescriptor> $fields */
    private function buildFactorySig(array $fields): string
    {
        if ($fields === []) {
            return '';
        }

        return ', ' . implode(', ', array_map(
            fn(FieldDescriptor $f) => "{$f->phpType()} \${$f->camelName()}",
            $fields,
        ));
    }

    /** @param list<FieldDescriptor> $fields */
    private function buildFactoryCall(array $fields): string
    {
        if ($fields === []) {
            return '';
        }

        return ', ' . implode(', ', array_map(
            fn(FieldDescriptor $f) => "\${$f->camelName()}",
            $fields,
        ));
    }
}
