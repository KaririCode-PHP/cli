# ADR-003 — GeneratorRegistry: Two-Phase Lifecycle with Auto-Seal

**Status:** Accepted
**Date:** 2026-03-08
**Component:** KaririCode\Cli V4.1

---

## Context

Code generation registries are mutable by nature during bootstrap (plugins register custom generators) but must be immutable during execution to prevent non-deterministic output. The original implementation allowed registration at any point, which could cause a test to register a mock generator mid-suite, leaking state into subsequent tests.

## Decision

GeneratorRegistry follows a two-phase lifecycle (ARFA 1.3 P1 — Immutable State Transformation):

**Phase 1 (builder):** `register()` accepts new generators. Default generators are registered in `__construct()`.

**Phase 2 (sealed):** The first call to `get()` auto-seals the registry. Any subsequent `register()` throws `the CLI exceptions (ADR-009)(8942)`.

```php
$registry = new GeneratorRegistry();           // Phase 1
$registry->register(GeneratorTarget::Dpo, $x); // OK
$gen = $registry->get(GeneratorTarget::Dpo);    // Auto-seals → Phase 2
$registry->register(GeneratorTarget::Dpo, $y);  // the CLI exceptions (ADR-009)(8942)
```

### Alignment

ARFA 1.3 P1: The registry transitions from mutable builder to immutable sealed state. Once sealed, it behaves as a pure lookup function with no side effects.

## Consequences

**Positive:**
- Guarantees deterministic output after first use
- Eliminates state leaks in test suites
- No explicit `seal()` call required from callers — auto-seals on demand

**Negative:**
- Plugin registrations must happen before first `get()` call
- Requires boot order awareness in the service provider

## Alternatives Considered

- **Builder returning a separate frozen registry** — more ceremony for a single bootstrap call site.
- **Mandatory explicit `seal()`** — easy to forget; auto-seal on first `get()` covers every path.
- **No sealing** — rejected: generators registered by one test leaked into the next.

## References

- Lippert, E. (2007). "Immutability in C#, Part One: Kinds of Immutability" — "popsicle" (freeze-after-init) immutability.
- Bloch, J. (2018). *Effective Java*, 3rd ed., Item 17 — "Minimize mutability".
