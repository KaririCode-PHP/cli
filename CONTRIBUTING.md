# Contributing to KaririCode\Cli

## Workflow

1. Branch from `main`; one concern per pull request.
2. Install the pinned toolchain once with `kcode init` (ADR-013), then run
   `composer check` (CS, PHPStan, Psalm, complexity, tests, security) and
   `composer infection` before pushing. CI runs the same gates.
3. Add a CHANGELOG entry under the unreleased version.
4. Architectural decisions get an ADR in `docs/adr/` named
   `ADR-NNN-slug.md`, with the sections Context, Decision, Consequences,
   Alternatives Considered and References, in that order.
5. Releases are signed tags (`git tag -s vX.Y.Z`).

## Code rules (Spec V4.3.3)

- `declare(strict_types=1)`, one type per file, concrete classes `final`,
  Value Objects `final readonly`, enums pure unless an external contract needs
  backing (E-003), no property hooks in readonly classes (E-040).
- Typed class constants; regexes end with `\z`; no `getX()` methods; no
  `object` parameters; named arguments for calls with three or more arguments.
- Type docblocks: role, ARFA principle and Spec classification,
  `Preconditions:`, `Thread-safety:`, `Side effects:`, `Example:`,
  `References:` with an `@see` URL. No `@package`, `@author`, `@license` or
  `@since`. Section markers are 72 columns.
- Exceptions: one `kariricode/contract` family per class, private constructor,
  static factories, codes in 8900–8999 with a `[CLI_89xx]` prefix, no rejected
  value in validation messages (ADR-009). New `catch` types must be added to
  `REVIEWED_CATCHES` in `tests/Conformance/SpecConformanceTest.php` with a
  reason in the pull request.
- Coupling to other components goes through `kariricode/contract`, a PSR or a
  CLI-owned port (ADR-011).
- Complexity budgets: cyclomatic ≤ 10, nesting ≤ 3, parameters ≤ 4,
  cognitive ≤ 15. A breach needs an entry with its reason in
  `tools/complexity-baseline.php`.

`kcli check:arfa --path=src` must report no violations on this repository.
