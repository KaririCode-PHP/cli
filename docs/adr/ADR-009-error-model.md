# ADR-009 — Error Model: Contract Exception Families in Block 8600–8699

**Status:** Accepted
**Date:** 2026-10-01

## Context

4.0 had one `CliException extends \RuntimeException` with codes 9200–9300.
That range collides with Tenancy (TN-9200–9299) and Parser (PS-9000–9999).
Spec V4.3.3 asks each exception to implement exactly one family of
`kariricode/contract` 2.11.0 (`Validation`, `NotFound`, `Configuration`,
`Protocol`, `ContainerFailure`, `Runtime`), to be built by static factories
behind a private constructor, and never to repeat a rejected value in its
message (E-047).

## Decision

The CLI takes block **8600–8699**, the next free block after Authenticator
(8400–8599). Messages start with `[CLI_86xx]`, as CircuitBreaker does with
`[CB_71xx]`.

| Range | Family | Class | Codes |
|---|---|---|---|
| 8600–8619 | `Validation` | `InvalidScaffoldRequestException` | 8600 class name, 8601 namespace, 8602 `--fields` entry |
| 8620–8639 | `NotFound` | `GeneratorNotFoundException` | 8620 no generator for the target |
| 8640–8659 | `Runtime` | `ScaffoldFailureException` | 8640 file exists, 8641 write failed, 8642 registry sealed |
| 8660–8699 | — | reserved | — |

4.0 → 4.1.0: 9200→8640, 9201→8641, 9202→8600, 9203→8602, 9204→8620,
9205→8601, 9206→8642. 9250 and 9300 had no throw site and are gone.

Validation factories take no arguments, so they cannot echo input. Commands
catch `KaririCode\Contract\Exception\ExceptionInterface` and exit 1;
`\JsonException` in `check:ecosystem` is the only other reviewed catch.

## Consequences

- Callers branch on the family (`instanceof Validation`) instead of a CLI
  class; the edge can map families to exit codes or HTTP statuses.
- `SpecConformanceTest` checks one family per class, private constructors,
  unique codes inside 8600–8699 and parameterless validation factories.
- Breaking for code that caught `CliException` or compared 92xx codes.
- The block still has to be entered in the Spec's error-code registry.

## Alternatives Considered

- **Keep 9200–9399.** Rejected: collides with two published blocks.
- **One exception class with a family per factory.** Impossible: a PHP class
  implements its interfaces for every instance.
- **Messages with the rejected value.** Rejected by E-047.

## References

- Spec V4.3.3, E-047; `kariricode/contract` 2.11.0 ADR-005 (exception families).
- ADR-003 (8642), ADR-005 (8640, 8641), ADR-008 (8600, 8601).
