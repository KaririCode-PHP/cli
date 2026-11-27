<?php

declare(strict_types=1);

namespace KaririCode\Cli\Generator;

use KaririCode\Cli\Contract\Generator;
use KaririCode\Cli\Enum\GeneratorTarget;
use KaririCode\Cli\Exception\GeneratorNotFoundException;
use KaririCode\Cli\Exception\ScaffoldFailureException;

// Concrete generators resolved from same namespace — no explicit use needed

/**
 * Registry that maps every GeneratorTarget to its Generator implementation.
 *
 * Two-phase lifecycle (ARFA 1.3 P1 — immutable after construction):
 *   Phase 1 (builder): register() is called from __construct → registerDefaults()
 *                      and optionally from caller code before first get() call.
 *   Phase 2 (sealed):  Once build() is called the registry is frozen.
 *                      Any subsequent register() throws an exception.
 *
 * ARFA 1.3: P1 (sealed after first use). Spec V4.3.3: service with two-phase
 * state (ADR-003).
 *
 * Preconditions: register() runs during bootstrap, before the first get().
 * Thread-safety: mutable until sealed; bootstrap it from one fiber.
 * Side effects: the first get() seals the registry.
 *
 * Example:
 *   $registry->get(GeneratorTarget::Dpo)->generate($context);
 *
 * References: ADR-003.
 * @see https://www.php.net/manual/en/language.enumerations.php
 */
final class GeneratorRegistry
{
    /** @var array<string, Generator> */
    private array $generators = [];

    private bool $sealed = false;

    public function __construct()
    {
        $this->registerDefaults();
    }

    // ── Builder (pre-seal only) ──────────────────────────────────────────

    /**
     * Registers a generator for a target.
     *
     * Must be called before seal(). Calling after seal() throws ScaffoldFailureException 8942.
     */
    public function register(GeneratorTarget $target, Generator $generator): void
    {
        if ($this->sealed) {
            throw ScaffoldFailureException::registrySealed($target->label());
        }

        $this->generators[$target->name] = $generator;
    }

    /**
     * Seals the registry against further modification.
     *
     * Called automatically by get() on first access if not already sealed.
     */
    public function seal(): void
    {
        $this->sealed = true;
    }

    // ── Dispatch ─────────────────────────────────────────────────────────

    public function get(GeneratorTarget $target): Generator
    {
        $this->seal(); // auto-seal on first read

        return $this->generators[$target->name]
            ?? throw GeneratorNotFoundException::forTarget($target->label());
    }

    // ── Defaults ─────────────────────────────────────────────────────────

    private function registerDefaults(): void
    {
        $this->register(GeneratorTarget::Dpo, new DpoGenerator());
        $this->register(GeneratorTarget::Command, new CommandGenerator());
        $this->register(GeneratorTarget::ValueObject, new ValueObjectGenerator());
        $this->register(GeneratorTarget::Entity, new EntityGenerator());
        $this->register(GeneratorTarget::Service, new ServiceGenerator());
        $this->register(GeneratorTarget::Repository, new RepositoryGenerator());
        $this->register(GeneratorTarget::Grammar, new GrammarGenerator());
        $this->register(GeneratorTarget::Visitor, new VisitorGenerator());
    }
}
