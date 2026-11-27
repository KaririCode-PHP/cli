# ADR-010 — Spec Quality Gates and Supply Chain in the Repository

**Status:** Accepted
**Date:** 2026-10-01

## Context

ADR-007 delegated every quality tool to KaririCode\Devkit and kept no
configuration in the repository. Spec V4.3.3 §29.1 names the Composer scripts
every component exposes, §13 sets complexity budgets and §32 asks for a
committed `composer.lock`, a CycloneDX 1.5 SBOM, `composer audit --locked`, a
secret scan, CI actions pinned by full commit SHA and signed release tags.
The conformance tests read these files, so they must live in the repository.
Project rule: where Devkit conventions conflict with the spec on component
tooling, the spec wins.

## Decision

- Scripts: `phpstan` (level max), `cs-check`/`cs-fix` (PER-CS 3.0),
  `complexity` (`tools/complexity.php`: cyclomatic ≤ 10, nesting ≤ 3,
  parameters ≤ 4, plus cognitive ≤ 15 via `phpstan-complexity.neon`), `test`,
  `test-conformance`, `infection` (MSI and covered MSI ≥ 80), `psalm` (level 1),
  `security` (`composer audit --locked`, `tools/secret-scan.php`,
  `phpunit --group security`), `sbom` (`tools/sbom.php`), `check`.
- Accepted complexity breaches live in `tools/complexity-baseline.php`, each
  with its reason.
- `composer.lock` is committed; `.gitignore` excludes only `vendor/` and
  build output. `php` is `>=8.4`, as in the rest of the ecosystem.
- `.github/workflows/ci.yml`: `permissions: contents: read`, every action
  pinned by commit SHA with the tag in a comment, Linux gate plus Windows tests.
- Devkit stays usable on a developer machine; it is not the gate.
- This ADR supersedes ADR-007.

## Consequences

- Anyone can run the same gates as CI with `composer check`.
- Configuration repeats across components, as in Console and CircuitBreaker.
- The lock cannot be produced until `kariricode/contract` 2.11.0 is tagged and
  `kariricode/console` 4.x resolves; QD-V433-COMPLIANCE tracks it.

## Alternatives Considered

- **Keep ADR-007.** Rejected: the gates would not be visible where the
  spec and its conformance tests look.
- **Commit Devkit-generated configuration.** Not chosen: it is not verified to
  cover the SBOM, the secret scan and the complexity budgets.

## References

- Spec V4.3.3 §13, §29.1, §32; ADR-007 (superseded).
- https://cyclonedx.org/specification/overview/
