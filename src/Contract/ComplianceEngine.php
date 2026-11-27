<?php

declare(strict_types=1);

namespace KaririCode\Cli\Contract;

use KaririCode\Cli\ValueObject\InspectionResult;

/**
 * Scans a source tree for ARFA 1.3 / Spec V4.3.3 violations.
 *
 * ARFA 1.3: P4 (protocol agnosticism). Spec V4.3.3: CLI-owned port that the
 * application or an adapter package implements (§31; ADR-011).
 *
 * Preconditions: the directory exists; rows carry code, severity, file, line and message.
 * Thread-safety: implementation-defined; the CLI calls it from one fiber.
 * Side effects: implementation-defined; the CLI only reads the result.
 *
 * Example:
 *   $result = (new RegexComplianceEngine())->scan('src');
 *
 * References: ADR-011 (component boundaries).
 * @see https://www.php.net/manual/en/language.oop5.interfaces.php
 */
interface ComplianceEngine
{
    /** Engine label reported in the summary (for example "regex" or "ast"). */
    public string $name { get; }

    public function scan(string $directory): InspectionResult;
}
