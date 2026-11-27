<?php

declare(strict_types=1);

namespace KaririCode\Cli\Contract;

use KaririCode\Cli\ValueObject\RouteEntry;

/**
 * Lists the application's routes for inspect:router.
 *
 * ARFA 1.3: P4 (protocol agnosticism). Spec V4.3.3: CLI-owned port that the
 * application or an adapter package implements (§31; ADR-011).
 *
 * Preconditions: routes are registered before the CLI runs.
 * Thread-safety: implementation-defined; the CLI calls it from one fiber.
 * Side effects: implementation-defined; the CLI only reads the result.
 *
 * Example:
 *   $app->add(new InspectRouterCommand(new RouterInspector($source), $renderer));
 *
 * References: ADR-011 (component boundaries).
 * @see https://www.php.net/manual/en/language.oop5.interfaces.php
 */
interface RouteSource
{
    /** @return iterable<RouteEntry> */
    public function routes(): iterable;
}
