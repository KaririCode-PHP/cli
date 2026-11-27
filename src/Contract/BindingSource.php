<?php

declare(strict_types=1);

namespace KaririCode\Cli\Contract;

use KaririCode\Cli\ValueObject\BindingEntry;

/**
 * Lists the application's container bindings for inspect:container.
 *
 * ARFA 1.3: P4 (protocol agnosticism). Spec V4.3.3: CLI-owned port that the
 * application or an adapter package implements (§31; ADR-011).
 *
 * Preconditions: bindings are known after the container is built.
 * Thread-safety: implementation-defined; the CLI calls it from one fiber.
 * Side effects: implementation-defined; the CLI only reads the result.
 *
 * Example:
 *   $app->add(new InspectContainerCommand(new ContainerInspector($source), $renderer));
 *
 * References: ADR-011 (component boundaries).
 * @see https://www.php.net/manual/en/language.oop5.interfaces.php
 */
interface BindingSource
{
    /** @return iterable<BindingEntry> */
    public function bindings(): iterable;
}
