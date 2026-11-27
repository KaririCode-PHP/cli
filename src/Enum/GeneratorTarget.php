<?php

declare(strict_types=1);

namespace KaririCode\Cli\Enum;

/**
 * Identifies the artifact type produced by a generator.
 *
 * ARFA 1.3: P1. Spec V4.3.3: pure enum (E-003: no external contract).
 *
 * Preconditions: none.
 * Thread-safety: immutable.
 * Side effects: none.
 *
 * Example:
 *   GeneratorTarget::Dpo->label(); // 'DPO'
 *
 * References: ADR-003.
 * @see https://www.php.net/manual/en/language.enumerations.php
 */
enum GeneratorTarget
{
    case Dpo;
    case Command;
    case ValueObject;
    case Entity;
    case Service;
    case Repository;
    case Middleware;
    case Event;
    case Listener;
    case Validator;
    case Sanitizer;
    case Transformer;
    case Grammar;
    case Visitor;

    public function label(): string
    {
        return match ($this) {
            self::Dpo         => 'DPO',
            self::Command     => 'Command',
            self::ValueObject => 'ValueObject',
            self::Entity      => 'Entity',
            self::Service     => 'Service',
            self::Repository  => 'Repository',
            self::Middleware  => 'Middleware',
            self::Event       => 'Event',
            self::Listener    => 'Listener',
            self::Validator   => 'Validator',
            self::Sanitizer   => 'Sanitizer',
            self::Transformer => 'Transformer',
            self::Grammar     => 'Grammar',
            self::Visitor     => 'Visitor',
        };
    }

    public function defaultSubDirectory(): string
    {
        return match ($this) {
            self::Dpo         => 'DataObject',
            self::Command     => 'Command',
            self::ValueObject => 'ValueObject',
            self::Entity      => 'Domain/Entity',
            self::Service     => 'Service',
            self::Repository  => 'Repository',
            self::Middleware  => 'Middleware',
            self::Event       => 'Event',
            self::Listener    => 'Listener',
            self::Validator   => 'Validator',
            self::Sanitizer   => 'Sanitizer',
            self::Transformer => 'Transformer',
            self::Grammar     => 'Parser/Grammar',
            self::Visitor     => 'Parser/Visitor',
        };
    }
}
