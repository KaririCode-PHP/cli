# ADR-012 — Documentation Elements, Generated Code and No Ambient Time

**Status:** Accepted
**Date:** 2026-10-01

## Context

Spec V4.3.3 §17 asks every type for its role, its ARFA principle and spec
classification, `Preconditions:` and `Thread-safety:`, `Side effects:`,
`Example:`, and `References:` with an `@see` URL, and forbids `@package`,
`@author`, `@license` and `@since`. §19.2 asks for injectable time. The 4.0
templates emitted `@author` and `@since` from `date('Y-m-d')`, and the
inspectors timed themselves with `hrtime()`.

## Decision

- All 54 source types carry the five elements; `check:arfa` enforces them
  (V10, V15) on any tree, the CLI's own included.
- Generated docblocks carry the same elements, filled for each artifact kind.
  `GeneratorContext` loses `author`, `date` and the unused `extraOption`.
- Durations are gone from `InspectionResult`, the table footer and the JSON
  payload (`duration_ms`), so no clock is needed.
- Section markers are 72 columns; constants are typed; regexes end with `\z`.

## Consequences

- Generated files pass `check:arfa` with no P0 and only intended P1 findings
  (V16): Value Object guards throw `\InvalidArgumentException` until the
  application supplies a `Validation` exception, and repository adapter stubs
  throw `\RuntimeException('Not implemented.')` until they are written.
- Generated repositories type the aggregate (`User $entity`) instead of
  `object` (§31); the developer imports it from its domain namespace.
- JSON consumers of 4.0 lose `duration_ms`; 4.1.0 is unreleased.

## Alternatives Considered

- **Inject a PSR-20 clock for durations.** Rejected: a dependency for a
  cosmetic figure.
- **Keep `@since` in generated code.** Rejected by §17.

## References

- Spec V4.3.3 §17, §19.2, E-040.
- ADR-005, ADR-008.
