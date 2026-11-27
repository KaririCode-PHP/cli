<?php

declare(strict_types=1);

namespace KaririCode\Cli\Enum;

/**
 * Output format for inspect commands.
 *
 * ARFA 1.3: P1. Spec V4.3.3: pure enum (E-003: no external contract).
 *
 * Preconditions: none.
 * Thread-safety: immutable.
 * Side effects: none.
 *
 * Example:
 *   OutputFormat::fromString('json'); // OutputFormat::Json
 *
 * References: ADR-004.
 * @see https://www.php.net/manual/en/language.enumerations.php
 */
enum OutputFormat
{
    case Table;
    case Tree;
    case Json;
    case Plain;

    public static function fromString(string $format): self
    {
        return match (strtolower($format)) {
            'table'       => self::Table,
            'tree'        => self::Tree,
            'json'        => self::Json,
            'plain', 'text' => self::Plain,
            default       => self::Table,
        };
    }
}
