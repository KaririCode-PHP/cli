# ADR-008 — Shared Scaffold Pipeline and Self-Validating Context

**Status:** Accepted
**Date:** 2026-09-29
**Component:** KaririCode\Cli V4.1

---

## Context

The seven `make:*` commands each copied the same generate → write → report loop. The review found four problems in them:

- Only `make:dpo` validated the class name. The other six emitted unparseable PHP for inputs such as `user-profile`, `List` or a namespace ending in `\`.
- No command caught `the CLI exceptions (ADR-009)`, so a pre-existing file or a malformed `--fields` token escaped as an unhandled exception.
- `make:repository` wrote the interface and then failed on the implementation, leaving a half-generated pair.
- Absolute `--path` values were glued onto `getcwd()`.

## Decision

1. `GeneratorContext` validates in its constructor: the class name must be PascalCase and not a PHP reserved word (8900), and the namespace must be backslash-separated identifiers (8901). An invalid context cannot exist.
2. `ScaffoldRunner` (composition, no base class) owns the pipeline: registry lookup → `Generator::generate()` → `FileWriter::writeAll()` → uniform status and dry-run output. It also normalises names (`className()`) and paths (`outputPath()`).
3. `FileWriter::writeAll()` runs a collision pre-flight over the whole batch before the first write.
4. Every `make:*` command catches `the CLI exceptions (ADR-009)`, prints `<error>` and returns exit code 1.
5. Write status is the pure enum `WriteStatus` instead of a string.

`make:service` was added on top of the runner. `ServiceGenerator` had been registered since 4.0.0 with no command to reach it.

### Alignment

ARFA 1.3 P1: the context is valid at construction and immutable afterwards. P4: `ScaffoldRunner` writes only through `Output` and `FileWriter`, so commands stay protocol-agnostic.

## Consequences

**Positive:**
- Each `make:*` command is under 25 lines of logic, and a new generator needs one command class.
- Output, exit codes and error messages are identical across generators.

**Negative:**
- Names are now normalised (`blade` → `BladeGrammar`, `user` → `User`) instead of rejected.

## Alternatives Considered

- **Abstract `MakeCommand` base class** — rejected by ADR-001 (zero inheritance).
- **Trait with the shared loop** — rejected: hidden coupling, and traits cannot enforce construction-time invariants.
- **Validation inside each command** — rejected: that is how six of seven ended up without it.

## References

- Evans, E. (2003). *Domain-Driven Design*, Ch. 6 — invariants enforced where objects are created.
- Gamma, E. et al. (1994). *Design Patterns* — "Favor object composition over class inheritance".
- ADR-001, ADR-005.
