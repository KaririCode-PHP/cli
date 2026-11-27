<?php

declare(strict_types=1);

namespace KaririCode\Cli\Application;

use KaririCode\Console\Contract\Input;

/**
 * Reads parsed console input as the scalar a command needs. Console 4.6
 * returns arguments and options as mixed; a command used to cast them, and
 * a cast turns an array or an object into "Array" or a fatal error. Here a
 * value of the wrong shape falls back to the default instead.
 *
 * ARFA 1.3: P4. Spec V4.3.3: stateless functions over the Input port; no
 * state, no I/O.
 *
 * Preconditions: the input was parsed against the command's definition.
 * Thread-safety: stateless.
 * Side effects: none.
 *
 * Example:
 *   $path = InputValue::text($input, 'path', 'src');
 *   $force = InputValue::flag($input, 'force');
 *
 * References: ADR-008; console SPEC-001 (Input port).
 * @see https://www.php.net/manual/en/language.types.type-juggling.php
 */
final class InputValue
{
    public static function text(Input $input, string $option, string $default): string
    {
        return self::scalarText($input->getOption($option)) ?? $default;
    }

    public static function argument(Input $input, string $name, string $default = ''): string
    {
        return self::scalarText($input->getArgument($name)) ?? $default;
    }

    public static function integer(Input $input, string $option, int $default): int
    {
        $text = self::scalarText($input->getOption($option));

        return $text !== null && preg_match('/\A-?\d+\z/', $text) === 1 ? (int) $text : $default;
    }

    /** A flag is set when the parser stored true, or a truthy string ("1", "true", "yes", "on"). */
    public static function flag(Input $input, string $option): bool
    {
        return self::isSet($input->getOption($option));
    }

    private static function isSet(mixed $value): bool
    {
        return $value === true || (is_string($value) && filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    private static function scalarText(mixed $value): ?string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : null;
    }
}
