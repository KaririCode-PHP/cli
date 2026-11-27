<?php

declare(strict_types=1);

namespace KaririCode\Cli\Contract;

/**
 * Renders the syntax tree of one PHP file for inspect:ast.
 *
 * ARFA 1.3: P4 (protocol agnosticism). Spec V4.3.3: CLI-owned port that the
 * application or an adapter package implements (§31; ADR-011).
 *
 * Preconditions: the file exists and is readable.
 * Thread-safety: implementation-defined; the CLI calls it from one fiber.
 * Side effects: implementation-defined; the CLI only reads the result.
 *
 * Example:
 *   echo $dumper->dump('src/User.php', 'tree', withPositions: false);
 *
 * References: ADR-011 (component boundaries).
 * @see https://www.php.net/manual/en/language.oop5.interfaces.php
 */
interface SyntaxTreeDumper
{
    /** @throws \KaririCode\Contract\Exception\ExceptionInterface when the file cannot be parsed */
    public function dump(string $file, string $format, bool $withPositions): string;
}
