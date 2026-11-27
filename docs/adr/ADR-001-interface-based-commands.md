# ADR-001 — Interface-Based Commands: Zero Inheritance

**Status:** Accepted  
**Date:** 2026-03-06  
**Component:** KaririCode\Cli V4.0

---

## Context

KaririCode\Cli V3.1 used an `AbstractCommand` base class, following the pattern established by Symfony Console (`extends Command`) and Laravel Artisan (`extends Illuminate\Console\Command`). This created tight coupling, made commands difficult to test, and violated ARFA 1.3 Principle 4 (Protocol Agnosticism).

## Decision

All Cli commands implement `KaririCode\Console\Contract\Command` directly. No `AbstractCommand` is provided. Constructor injection delivers all dependencies.

```php
// ❌ V3.1 — inheritance required
final class MakeDpoCommand extends AbstractCommand { ... }

// ✅ V4.0 — interface only
#[AsCommand(name: 'make:dpo', description: '...')]
final class MakeDpoCommand implements Command
{
    public function __construct(
        private readonly GeneratorRegistry $generators,
        private readonly FieldInferenceEngine $inference,
    ) {}
}
```

### Alignment

ARFA 1.3 P4 (Protocol Agnosticism): A command class has no dependency on being run from a CLI process. `ArrayInput` + `NullOutput` can drive it from tests or an HTTP endpoint.

## Consequences

**Positive:**
- Commands are plain PHP objects — no framework coupling
- Constructor injection is explicit and verifiable
- PHPUnit can instantiate commands with mock dependencies
- Protocol agnosticism: same command class can run via HTTP, Queue, or CLI

**Negative:**
- No shared output helper methods (e.g. `$this->info()`) — callers write to `Output` directly
- Slightly more verbose at call sites

## Alternatives Considered

- **Abstract base command** (Symfony `Command`, Laravel `Illuminate\Console\Command`) — rejected: couples every command to a framework lifecycle through template-method hooks.
- **Traits for shared helpers** — rejected: implicit coupling that the interface cannot express.
- **Composition** — adopted for shared behaviour (`ScaffoldRunner`, ADR-008).

## References

- Gamma, E. et al. (1994). *Design Patterns* — "Favor object composition over class inheritance".
- ADR-008 — shared scaffold pipeline built by composition.

## Amendment — 2026-11-27: console 4.6 Command contract

Console 4.6 moved the command's name and description onto the interface
(`public string $name { get; }`, `public string $description { get; }`,
console ADR-009), and `execute()` returns the `ExitCode` enum (`Success` 0,
`Failure` 1, `Invalid` 2, console ADR-002) instead of `int`. The reviewed
4.1.0 was written against 4.1, so `bin/kcli` stopped with a fatal error on
the first command it loaded. The fourteen commands now declare both
properties, with the values their `#[AsCommand]` attributes already carried,
and return `ExitCode`. `check:arfa --fail-on-p1` keeps status 2 for P1-only
findings through `ExitCode::Invalid`. Arguments and options arrive as
`mixed`; `Application\InputValue` turns them into the scalar a command needs
and falls back to the default for a value of another shape, where a cast
would have produced "Array". `make:command` generates the same shape.
`tests/Unit/Command/ConsoleContractTest.php` checks every command against the
contract.
