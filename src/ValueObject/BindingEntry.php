<?php

declare(strict_types=1);

namespace KaririCode\Cli\ValueObject;

/**
 * One container binding as reported by a BindingSource.
 *
 * ARFA 1.3: P1 (immutable state). Spec V4.3.3: Value Object, final readonly.
 *
 * Preconditions: values come from a BindingSource implementation.
 * Thread-safety: immutable; safe to share between fibers.
 * Side effects: none.
 *
 * Example:
 *   new BindingEntry(abstract: LoggerInterface::class, concrete: FileLogger::class, singleton: true);
 *
 * References: ADR-011.
 * @see https://www.php.net/manual/en/language.oop5.properties.php#language.oop5.properties.readonly-properties
 */
final readonly class BindingEntry
{
    public function __construct(
        public string $abstract,
        public string $concrete,
        public bool $singleton = false,
    ) {}
}
