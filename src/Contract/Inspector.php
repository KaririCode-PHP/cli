<?php

declare(strict_types=1);

namespace KaririCode\Cli\Contract;

use KaririCode\Cli\ValueObject\InspectionResult;

/**
 * Produces a format-agnostic InspectionResult for one component.
 *
 * ARFA 1.3: P4 (protocol agnosticism). Spec V4.3.3: CLI-owned port.
 *
 * Preconditions: the inspected source or port was injected at construction.
 * Thread-safety: implementations hold no mutable state.
 * Side effects: implementation-defined reads; never writes.
 *
 * Example:
 *   $renderer->render($inspector->inspect(), $output, OutputFormat::Table);
 *
 * References: ADR-004, ADR-011.
 * @see https://www.php.net/manual/en/language.oop5.interfaces.php
 */
interface Inspector
{
    /** Name shown in the header of the rendered result. */
    public string $componentName { get; }

    public function inspect(): InspectionResult;
}
