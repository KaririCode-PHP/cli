<?php

declare(strict_types=1);

namespace KaririCode\Cli\Exception;

use KaririCode\Contract\Exception\NotFound;

/**
 * No generator is registered for the requested GeneratorTarget.
 *
 * ARFA 1.3: P4. Spec V4.3.3: exception of the NotFound family, codes
 * 8920–8939 of the CLI block.
 *
 * Preconditions: the target label comes from the GeneratorTarget enum, never
 * from raw user input.
 * Thread-safety: immutable after construction.
 * Side effects: none.
 *
 * Example:
 *   throw GeneratorNotFoundException::forTarget(GeneratorTarget::Transformer->label());
 *
 * References: ADR-003, ADR-009.
 * @see https://www.php.net/manual/en/language.exceptions.extending.php
 */
final class GeneratorNotFoundException extends \RuntimeException implements NotFound
{
    private function __construct(string $message, int $code)
    {
        parent::__construct($message, $code);
    }

    public static function forTarget(string $target): self
    {
        return new self("[CLI_8920] No generator is registered for target {$target}.", 8920);
    }
}
