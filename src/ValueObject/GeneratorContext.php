<?php

declare(strict_types=1);

namespace KaririCode\Cli\ValueObject;

use KaririCode\Cli\Enum\GeneratorTarget;
use KaririCode\Cli\Exception\InvalidScaffoldRequestException;

/**
 * Immutable snapshot of a code generation request.
 *
 * Constructed once from CLI input and passed through the generator
 * chain without mutation (ARFA 1.3 P1). The constructor rejects class
 * names and namespaces that would produce unparseable PHP, so every
 * make:* command gets the same validation instead of re-implementing it.
 *
 * ARFA 1.3: P1 (immutable state). Spec V4.3.3: Value Object, final readonly.
 *
 * Preconditions: PascalCase class name that is not reserved (8600); namespace of PHP
 * identifiers (8601).
 * Thread-safety: immutable; safe to share between fibers.
 * Side effects: none.
 *
 * Example:
 *   new GeneratorContext(className: 'User', namespace: 'App\Domain', target: GeneratorTarget::Entity, outputPath: 'src/Domain');
 *
 * References: ADR-008.
 * @see https://www.php.net/manual/en/language.oop5.properties.php#language.oop5.properties.readonly-properties
 */
final readonly class GeneratorContext
{
    /** Keywords and reserved type names PHP rejects as class names (case-insensitive). */
    private const array RESERVED_WORDS = [
        'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case',
        'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default',
        'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor',
        'endforeach', 'endif', 'endswitch', 'endwhile', 'eval', 'exit',
        'extends', 'false', 'final', 'finally', 'float', 'fn', 'for',
        'foreach', 'function', 'global', 'goto', 'if', 'implements',
        'include', 'instanceof', 'insteadof', 'int', 'interface', 'isset',
        'iterable', 'list', 'match', 'mixed', 'namespace', 'never', 'new',
        'null', 'object', 'or', 'parent', 'print', 'private', 'protected',
        'public', 'readonly', 'require', 'return', 'self', 'static', 'string',
        'switch', 'throw', 'trait', 'true', 'try', 'unset', 'use', 'var',
        'void', 'while', 'xor', 'yield',
    ];

    /**
     * @param list<FieldDescriptor> $fields
     *
     * @throws InvalidScaffoldRequestException 8600 (class name) or 8601 (namespace)
     */
    public function __construct(
        public string $className,
        public string $namespace,
        public GeneratorTarget $target,
        public string $outputPath,
        public array $fields = [],
        public bool $dryRun = false,
        public bool $force = false,
    ) {
        if (
            preg_match('/^[A-Z][A-Za-z0-9]*\z/', $className) !== 1
            || in_array(strtolower($className), self::RESERVED_WORDS, true)
        ) {
            throw InvalidScaffoldRequestException::invalidClassName();
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/', $namespace) !== 1) {
            throw InvalidScaffoldRequestException::invalidNamespace();
        }
    }

    // ── Derived ──────────────────────────────────────────────────────────

    public function fullyQualifiedClassName(): string
    {
        return $this->namespace . '\\' . $this->className;
    }

    public function targetFilePath(): string
    {
        return rtrim($this->outputPath, '/') . '/' . $this->className . '.php';
    }

    public function hasFields(): bool
    {
        return $this->fields !== [];
    }
}
