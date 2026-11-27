<?php

declare(strict_types=1);

namespace KaririCode\Cli\Generator;

use KaririCode\Cli\Contract\Generator;
use KaririCode\Cli\ValueObject\FieldDescriptor;
use KaririCode\Cli\ValueObject\GeneratedFile;
use KaririCode\Cli\ValueObject\GeneratorContext;

/**
 * Generates a final readonly DPO class (Data Preparation Object).
 *
 * The DPO pattern is KaririCode's alternative to DTOs/FormRequests.
 * It collocates data structure with processing attributes (#[Validate],
 * #[Sanitize]) in a single, immutable, constructor-promoted readonly class.
 *
 * Generated DPOs include:
 *   - final readonly class (immutable by construction)
 *   - Constructor-promoted typed properties
 *   - #[Validate] and #[Sanitize] attributes inferred from field names
 *   - #[MapInput] for Console's InputMapper binding
 *   - Spec V4.3.3 documentation elements (§17)
 *
 * The rules are named, not implemented: kariricode/validator and
 * kariricode/sanitizer provide them.
 *
 * ARFA 1.3: P1 (the output depends only on the GeneratorContext).
 * Spec V4.3.3: stateless generator, final class.
 *
 * Preconditions: the fields come from FieldInferenceEngine (ADR-002).
 * Thread-safety: stateless; safe to share between fibers.
 * Side effects: none; FileWriter writes the returned files (ADR-005).
 *
 * Example:
 *   $files = (new DpoGenerator())->generate($context);
 *
 * References: ADR-002, ADR-012.
 * @see https://www.php.net/manual/en/language.oop5.decon.php#language.oop5.decon.constructor.promotion
 */
final class DpoGenerator implements Generator
{
    /** Indentation of a constructor-promoted parameter (class 0 → method 4 → param 8). */
    private const string PARAM_INDENT = '        ';

    public string $type {
        get => 'DPO';
    }

    // ── Generator contract ───────────────────────────────────────────────

    #[\Override]
    public function generate(GeneratorContext $context): array
    {
        $content = $this->renderDpo($context);

        return [
            new GeneratedFile(
                path: $context->targetFilePath(),
                content: $content,
                label: "DPO: {$context->fullyQualifiedClassName()}",
            ),
        ];
    }

    // ── Template ─────────────────────────────────────────────────────────

    private function renderDpo(GeneratorContext $context): string
    {
        $namespace  = $context->namespace;
        $className  = $context->className;
        $useBlock   = $this->buildUseBlock($context);
        $properties = $this->buildProperties($context->fields);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        {$useBlock}

        /**
         * Data Preparation Object for {$className}.
         *
         * Immutable command carrier validated and sanitized via the
         * KaririCode\ProcessorPipeline before reaching domain services.
         * All fields are set once in the constructor and sealed by PHP 8.4.
         *
         * ARFA 1.3: P1 (immutable state). Spec V4.3.3: DPO, final readonly.
         *
         * Preconditions: values passed the #[Validate] rules of each field.
         * Thread-safety: immutable; safe to share between fibers.
         * Side effects: none.
         *
         * Example:
         *   \$dpo = new {$className}(...);
         *
         * References:
         * @see https://www.php.net/manual/en/language.oop5.properties.php#language.oop5.properties.readonly-properties
         */
        #[MapInput]
        final readonly class {$className}
        {
            public function __construct(
        {$properties}
            ) {}
        }
        PHP;
    }

    private function buildUseBlock(GeneratorContext $context): string
    {
        $uses = [
            'KaririCode\\Console\\Attribute\\MapInput',
        ];

        $hasProcessors = false;

        foreach ($context->fields as $field) {
            if ($field->validators !== [] || $field->sanitizers !== []) {
                $hasProcessors = true;
                break;
            }
        }

        if ($hasProcessors) {
            $uses[] = 'KaririCode\\Sanitizer\\Attribute\\Sanitize';
            $uses[] = 'KaririCode\\Validator\\Attribute\\Validate';
        }

        sort($uses);

        return implode("\n", array_map(fn($u) => "use {$u};", $uses));
    }

    /** @param list<FieldDescriptor> $fields */
    private function buildProperties(array $fields): string
    {
        if ($fields === []) {
            return self::PARAM_INDENT . '// TODO: Add fields';
        }

        $lines = [];

        foreach ($fields as $field) {
            $lines[] = $this->renderProperty($field);
        }

        return implode("\n\n", $lines);
    }

    private function renderProperty(FieldDescriptor $field): string
    {
        $indent = self::PARAM_INDENT;
        $lines  = [];

        if ($field->validators !== []) {
            $rules   = implode(', ', array_map(fn($v) => "'{$v}'", $field->validators));
            $lines[] = "{$indent}#[Validate([{$rules}])]";
        }

        if ($field->sanitizers !== []) {
            $rules   = implode(', ', array_map(fn($s) => "'{$s}'", $field->sanitizers));
            $lines[] = "{$indent}#[Sanitize([{$rules}])]";
        }

        $type     = $field->phpType();
        $name     = $field->camelName();
        $required = $field->required && $field->default === null;

        if ($required) {
            $lines[] = "{$indent}public {$type} \${$name},";
        } else {
            $default  = $this->renderDefault($field->default);
            $nullable = $field->default === null ? '?' : '';
            $lines[]  = "{$indent}public {$nullable}{$type} \${$name} = {$default},";
        }

        return implode("\n", $lines);
    }

    private function renderDefault(mixed $default): string
    {
        if ($default === null) {
            return 'null';
        }
        if (is_bool($default)) {
            return $default ? 'true' : 'false';
        }
        if (is_string($default)) {
            return "'" . addslashes($default) . "'";
        }
        if (is_array($default)) {
            return '[]';
        }

        return is_scalar($default) ? (string) $default : 'null';
    }
}
