<?php

declare(strict_types=1);

namespace KaririCode\Cli\Enum;

/**
 * PHP primitive and domain types supported by the field inference engine.
 *
 * Pure enum — no backing values (E-003).
 *
 * ARFA 1.3: P1. Spec V4.3.3: pure enum (E-003: no external contract).
 *
 * Preconditions: none.
 * Thread-safety: immutable.
 * Side effects: none.
 *
 * Example:
 *   FieldType::fromString('email')->toPhpType(); // 'string'
 *
 * References: ADR-002.
 * @see https://www.php.net/manual/en/language.enumerations.php
 */
enum FieldType
{
    case String;
    case Int;
    case Float;
    case Bool;
    case Array;
    case DateTimeImmutable;
    case Uuid;
    case Email;
    case Url;
    case Phone;
    case Cpf;
    case Cnpj;
    case Money;

    // ── Coercions ────────────────────────────────────────────────────────

    public function toPhpType(): string
    {
        return match ($this) {
            self::String, self::Email, self::Url,
            self::Phone, self::Cpf, self::Cnpj => 'string',
            self::Int                          => 'int',
            self::Float, self::Money           => 'float',
            self::Bool                         => 'bool',
            self::Array                        => 'array',
            self::DateTimeImmutable            => '\DateTimeImmutable',
            self::Uuid                         => 'string',
        };
    }

    public static function fromString(string $type): self
    {
        return match (strtolower($type)) {
            'string', 'str'             => self::String,
            'int', 'integer'            => self::Int,
            'float', 'double', 'number' => self::Float,
            'bool', 'boolean'           => self::Bool,
            'array', 'list'             => self::Array,
            'datetime', 'date'          => self::DateTimeImmutable,
            'uuid'                      => self::Uuid,
            'email'                     => self::Email,
            'url', 'uri'                => self::Url,
            'phone'                     => self::Phone,
            'cpf'                       => self::Cpf,
            'cnpj'                      => self::Cnpj,
            'money', 'currency'         => self::Money,
            default                     => self::String,
        };
    }
}
