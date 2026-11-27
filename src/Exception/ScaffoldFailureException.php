<?php

declare(strict_types=1);

namespace KaririCode\Cli\Exception;

use KaririCode\Contract\Exception\Runtime;

/**
 * A scaffold could not be written or the registry was used out of order.
 *
 * ARFA 1.3: P1 (sealed registry), P4 (I/O isolated in FileWriter).
 * Spec V4.3.3: exception of the Runtime family, codes 8940–8959.
 *
 * Preconditions: paths are generated targets, not rejected input.
 * Thread-safety: immutable after construction.
 * Side effects: none.
 *
 * Example:
 *   throw ScaffoldFailureException::fileExists('/app/src/User.php');
 *
 * References: ADR-005, ADR-009.
 * @see https://www.php.net/manual/en/function.file-put-contents.php
 */
final class ScaffoldFailureException extends \RuntimeException implements Runtime
{
    private function __construct(string $message, int $code)
    {
        parent::__construct($message, $code);
    }

    public static function fileExists(string $path): self
    {
        return new self("[CLI_8940] Target file already exists: {$path}. Use --force to overwrite.", 8940);
    }

    public static function writeFailed(string $path, string $reason): self
    {
        return new self("[CLI_8941] Cannot write {$path}: {$reason}.", 8941);
    }

    public static function registrySealed(string $target): self
    {
        return new self("[CLI_8942] The generator registry is sealed after first use; register {$target} during bootstrap.", 8942);
    }
}
