<?php

declare(strict_types=1);

namespace KaririCode\Cli\Contract;

use KaririCode\Cli\ValueObject\GeneratedFile;
use KaririCode\Cli\ValueObject\GeneratorContext;

/**
 * Turns a validated GeneratorContext into PHP source files without I/O.
 *
 * ARFA 1.3: P4 (protocol agnosticism). Spec V4.3.3: CLI-owned port;
 * implementations are stateless final classes.
 *
 * Preconditions: the context was validated at construction (ADR-008).
 * Thread-safety: implementations are stateless and safe to share.
 * Side effects: none; FileWriter performs every write (ADR-005).
 *
 * Example:
 *   $files = (new DpoGenerator())->generate($context);
 *
 * References: ADR-005, ADR-008.
 * @see https://www.php.net/manual/en/language.types.string.php#language.types.string.syntax.heredoc
 */
interface Generator
{
    /** Display label of the generated artifact, for example "DPO". */
    public string $type { get; }

    /** @return list<GeneratedFile> */
    public function generate(GeneratorContext $context): array;
}
