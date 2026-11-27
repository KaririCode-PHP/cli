<?php

declare(strict_types=1);

namespace KaririCode\Cli\Exception;

use KaririCode\Contract\Exception\Validation;

/**
 * Rejected scaffolding input: class name, namespace or a --fields entry.
 *
 * ARFA 1.3: P1 (raised before any state exists). Spec V4.3.3: exception
 * of the Validation family, codes 8600–8619 of the CLI block.
 *
 * Preconditions: none.
 * Thread-safety: immutable after construction.
 * Side effects: none. Messages never repeat the rejected value (E-047).
 *
 * Example:
 *   throw InvalidScaffoldRequestException::invalidClassName();
 *
 * References: ADR-009 (error model).
 * @see https://www.php.net/manual/en/language.exceptions.extending.php
 */
final class InvalidScaffoldRequestException extends \InvalidArgumentException implements Validation
{
    private function __construct(string $message, int $code)
    {
        parent::__construct($message, $code);
    }

    public static function invalidClassName(): self
    {
        return new self('[CLI_8600] Invalid class name: use PascalCase letters and digits and avoid PHP reserved words.', 8600);
    }

    public static function invalidNamespace(): self
    {
        return new self('[CLI_8601] Invalid namespace: use backslash-separated PHP identifiers, such as App\\Domain\\User.', 8601);
    }

    public static function invalidFieldDefinition(): self
    {
        return new self('[CLI_8602] Invalid --fields entry: expected name or name:type, such as email:string.', 8602);
    }
}
