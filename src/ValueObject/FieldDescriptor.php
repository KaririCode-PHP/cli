<?php

declare(strict_types=1);

namespace KaririCode\Cli\ValueObject;

use KaririCode\Cli\Enum\FieldType;

/**
 * Immutable descriptor for a single field in a generated class.
 *
 * Carries the field name, inferred type, and the processing attributes
 * (validators/sanitizers) that the inference engine resolved. Created
 * by FieldInferenceEngine or explicitly by the make command.
 *
 * ARFA 1.3: P1 (immutable state). Spec V4.3.3: Value Object, final readonly.
 *
 * Preconditions: built by FieldInferenceEngine from a name[:type] token.
 * Thread-safety: immutable; safe to share between fibers.
 * Side effects: none.
 *
 * Example:
 *   new FieldDescriptor(name: 'email', type: FieldType::Email);
 *
 * References: ADR-002.
 * @see https://www.php.net/manual/en/language.oop5.properties.php#language.oop5.properties.readonly-properties
 */
final readonly class FieldDescriptor
{
    /**
     * @param list<string> $validators  Validator class names without namespace prefix.
     * @param list<string> $sanitizers  Sanitizer class names without namespace prefix.
     */
    public function __construct(
        public string $name,
        public FieldType $type,
        public bool $required = true,
        public mixed $default = null,
        public array $validators = [],
        public array $sanitizers = [],
        public ?string $docBlock = null,
    ) {}

    // ── Factories ────────────────────────────────────────────────────────

    public static function fromParsedString(string $name, string $typeStr): self
    {
        return new self(
            name: $name,
            type: FieldType::fromString($typeStr),
        );
    }

    // ── Derived accessors ────────────────────────────────────────────────

    public function phpType(): string
    {
        return $this->type->toPhpType();
    }

    public function hasProcessors(): bool
    {
        return $this->validators !== [] || $this->sanitizers !== [];
    }

    public function camelName(): string
    {
        return lcfirst(str_replace('_', '', ucwords($this->name, '_')));
    }
}
