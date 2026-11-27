<?php

declare(strict_types=1);

namespace KaririCode\Cli\ValueObject;

/**
 * Immutable payload produced by an Inspector.
 *
 * ARFA 1.3: P1 (immutable state). Spec V4.3.3: Value Object, final readonly.
 *
 * Preconditions: rows share the same keys; summary values are scalars.
 * Thread-safety: immutable; safe to share between fibers.
 * Side effects: none.
 *
 * Example:
 *   $count = $result->rowCount();
 *
 * References: ADR-004.
 * @see https://www.php.net/manual/en/language.oop5.properties.php#language.oop5.properties.readonly-properties
 */
final readonly class InspectionResult
{
    /**
     * @param list<array<string, scalar|null>> $rows    Tabular data for table/tree rendering.
     * @param array<string, scalar|null>       $summary Key metrics shown in the header.
     */
    public function __construct(
        public string $componentName,
        public array $rows,
        public array $summary = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }
}
