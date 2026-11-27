# ADR-009 — Error Model: Contract Exception Families in Block 8900–8999

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

The CLI takes block **8900–8999** of the registry of record,
`kariricode/exception` `CODE-BLOCKS.md` (see the amendment below). Messages
start with `[CLI_89xx]`, as CircuitBreaker does with `[CB_71xx]`.

| Range | Family | Class | Codes |
|---|---|---|---|
| 8900–8919 | `Validation` | `InvalidScaffoldRequestException` | 8900 class name, 8901 namespace, 8902 `--fields` entry |
| 8920–8939 | `NotFound` | `GeneratorNotFoundException` | 8920 no generator for the target |
| 8940–8959 | `Runtime` | `ScaffoldFailureException` | 8940 file exists, 8941 write failed, 8942 registry sealed |
| 8960–8999 | — | reserved | — |

4.0 → 4.1.0: 9200→8940, 9201→8941, 9202→8900, 9203→8902, 9204→8920,
9205→8901, 9206→8942. 9250 and 9300 had no throw site and are gone.

Validation factories take no arguments, so they cannot echo input. Commands
catch `KaririCode\Contract\Exception\ExceptionInterface` and exit 1;
`\JsonException` in `check:ecosystem` is the only other reviewed catch.

## Consequences

- Callers branch on the family (`instanceof Validation`) instead of a CLI
  class; the edge can map families to exit codes or HTTP statuses.
- `SpecConformanceTest` checks one family per class, private constructors,
  unique codes inside 8900–8999 and parameterless validation factories.
- Breaking for code that caught `CliException` or compared 92xx codes.
- The block still has to be entered in the Spec's error-code registry.

## Alternatives Considered

- **Keep 9200–9399.** Rejected: collides with two published blocks.
- **One exception class with a family per factory.** Impossible: a PHP class
  implements its interfaces for every instance.
- **Messages with the rejected value.** Rejected by E-047.

## References

- Spec V4.3.3, E-047; `kariricode/contract` 2.11.0 ADR-005 (exception families).
- ADR-003 (8942), ADR-005 (8940, 8941), ADR-008 (8900, 8901).

## Amendment — 2026-11-27: block 8900–8999

The reviewed 4.1.0 took 8600–8699, which kariricode/cache 4.1.0 also claimed
and released first. Rule 5 of the registry gives a block to the component
that released codes from it, so the CLI takes the nearest free block of the
same size, 8900–8999, and every code moves by +300 (8600 → 8900, 8620 →
8920, 8640 → 8940): the layout of the families inside the block is
unchanged. 4.1.0 is the first release, so no released code changes.
