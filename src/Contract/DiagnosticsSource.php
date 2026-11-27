<?php

declare(strict_types=1);

namespace KaririCode\Cli\Contract;

use KaririCode\Cli\ValueObject\InspectionResult;

/**
 * Runs source diagnostics over a file or directory for inspect:diagnostics.
 *
 * ARFA 1.3: P4 (protocol agnosticism). Spec V4.3.3: CLI-owned port that the
 * application or an adapter package implements (§31; ADR-011).
 *
 * Preconditions: minimum severity is error, warning, info or hint; summary['errors'] counts error-level findings.
 * Thread-safety: implementation-defined; the CLI calls it from one fiber.
 * Side effects: implementation-defined; the CLI only reads the result.
 *
 * Example:
 *   $result = $source->diagnose('src', 'warning', maxFiles: 0);
 *
 * References: ADR-011 (component boundaries).
 * @see https://www.php.net/manual/en/language.oop5.interfaces.php
 */
interface DiagnosticsSource
{
    /**
     * @param 'error'|'warning'|'info'|'hint' $minimumSeverity
     * @param int<0, max>                    $maxFiles 0 means no limit
     */
    public function diagnose(string $path, string $minimumSeverity, int $maxFiles): InspectionResult;
}
