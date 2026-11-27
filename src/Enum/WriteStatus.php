<?php

declare(strict_types=1);

namespace KaririCode\Cli\Enum;

/**
 * Outcome of a single FileWriter operation.
 *
 * Pure enum (E-003): the status never crosses a wire or storage
 * boundary, so the case name is the identity. label() provides the
 * fixed-width uppercase text shown in terminal output.
 *
 * ARFA 1.3: P1. Spec V4.3.3: pure enum (E-003: no external contract).
 *
 * Preconditions: none.
 * Thread-safety: immutable.
 * Side effects: none.
 *
 * Example:
 *   WriteStatus::DryRun->label(); // 'DRY-RUN'
 *
 * References: ADR-005.
 * @see https://www.php.net/manual/en/language.enumerations.php
 */
enum WriteStatus
{
    case Created;
    case Updated;
    case DryRun;

    public function label(): string
    {
        return match ($this) {
            self::Created => 'CREATED',
            self::Updated => 'UPDATED',
            self::DryRun  => 'DRY-RUN',
        };
    }
}
