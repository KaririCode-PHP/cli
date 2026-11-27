<?php

declare(strict_types=1);

namespace KaririCode\Cli\Application;

use KaririCode\Cli\Enum\WriteStatus;

/**
 * Result of writing (or previewing) one generated file.
 *
 * ARFA 1.3: P1 (immutable state). Spec V4.3.3: Value Object, final readonly.
 *
 * Preconditions: content is set only in dry-run mode.
 * Thread-safety: immutable; safe to share between fibers.
 * Side effects: none.
 *
 * Example:
 *   $outcome->isDryRun();
 *
 * References: ADR-005.
 * @see https://www.php.net/manual/en/language.oop5.properties.php#language.oop5.properties.readonly-properties
 */
final readonly class WriteOutcome
{
    public function __construct(
        public string $path,
        public WriteStatus $status,
        /** Non-null in dry-run mode — the full PHP source. */
        public ?string $content,
        public int $lineCount,
    ) {}

    public function isDryRun(): bool
    {
        return $this->status === WriteStatus::DryRun;
    }
}
