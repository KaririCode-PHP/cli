<?php

declare(strict_types=1);

namespace KaririCode\Cli\Enum;

/**
 * Severity of an ARFA 1.3 / Spec V4.3.3 compliance violation.
 *
 * P0 is build-breaking; P1 requires review. Lower rank means more severe,
 * so a threshold of P1 includes both levels and a threshold of P0 includes
 * only P0. Encoding the ordering here (instead of comparing strings at the
 * call site) is what keeps the check:arfa filter from inverting again.
 *
 * Pure enum (E-003): internal ordering, no external contract.
 *
 * ARFA 1.3: P1. Spec V4.3.3: pure enum (E-003: no external contract).
 *
 * Preconditions: none.
 * Thread-safety: immutable.
 * Side effects: none.
 *
 * Example:
 *   ViolationSeverity::P1->includes(ViolationSeverity::P0); // true
 *
 * References: SPEC-001 §5.
 * @see https://www.php.net/manual/en/language.enumerations.php
 */
enum ViolationSeverity
{
    case P0;
    case P1;

    /** Unknown labels fall back to P1 (review), never silently to P0. */
    public static function fromLabel(string $label): self
    {
        return match (strtoupper(trim($label))) {
            'P0'    => self::P0,
            default => self::P1,
        };
    }

    public function label(): string
    {
        return $this->name;
    }

    public function rank(): int
    {
        return match ($this) {
            self::P0 => 0,
            self::P1 => 1,
        };
    }

    /** True when $severity is at least as severe as this threshold. */
    public function includes(self $severity): bool
    {
        return $severity->rank() <= $this->rank();
    }
}
