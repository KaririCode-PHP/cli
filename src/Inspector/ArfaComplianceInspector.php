<?php

declare(strict_types=1);

namespace KaririCode\Cli\Inspector;

use KaririCode\Cli\Contract\ComplianceEngine;
use KaririCode\Cli\Contract\Inspector;
use KaririCode\Cli\ValueObject\InspectionResult;

/**
 * Runs a ComplianceEngine over one source tree for check:arfa.
 *
 * ARFA 1.3: P4. Spec V4.3.3: stateless service. The built-in regex engine
 * is the default; an AST engine plugs in through the same port instead of
 * a class_exists() probe for kariricode/parser (ADR-004, ADR-011).
 *
 * Preconditions: none; a missing directory yields summary['error'].
 * Thread-safety: holds only its path and engine.
 * Side effects: the engine reads files under the path.
 *
 * Example:
 *   $result = (new ArfaComplianceInspector('src'))->inspect();
 *
 * References: ADR-004.
 * @see https://www.pcre.org/current/doc/html/pcre2pattern.html
 */
final class ArfaComplianceInspector implements Inspector
{
    public string $componentName {
        get => 'ARFA 1.3 / Spec V4.3.3 compliance';
    }

    public function __construct(
        private readonly string $sourcePath,
        private readonly ComplianceEngine $engine = new RegexComplianceEngine(),
    ) {}

    #[\Override]
    public function inspect(): InspectionResult
    {
        return $this->engine->scan($this->sourcePath);
    }
}
