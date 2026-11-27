<?php

declare(strict_types=1);

namespace KaririCode\Cli\ValueObject;

/**
 * Immutable record of a source file produced by a generator.
 *
 * Contains the resolved path and the full PHP source code. The caller
 * decides whether to write to disk, print in dry-run mode, or assert
 * in a test — the generator itself has no filesystem dependency
 * (ARFA 1.3 P4 — protocol agnosticism).
 *
 * ARFA 1.3: P1 (immutable state). Spec V4.3.3: Value Object, final readonly.
 *
 * Preconditions: the path is absolute or relative to the working directory.
 * Thread-safety: immutable; safe to share between fibers.
 * Side effects: none.
 *
 * Example:
 *   $lines = $file->lineCount();
 *
 * References: ADR-005.
 * @see https://www.php.net/manual/en/language.oop5.properties.php#language.oop5.properties.readonly-properties
 */
final readonly class GeneratedFile
{
    public function __construct(
        public string $path,
        public string $content,
        /** Human-readable label shown in dry-run output. */
        public string $label,
    ) {}

    // ── Accessors ────────────────────────────────────────────────────────

    public function filename(): string
    {
        return basename($this->path);
    }

    public function directory(): string
    {
        return dirname($this->path);
    }

    public function lineCount(): int
    {
        return substr_count($this->content, "\n") + 1;
    }
}
