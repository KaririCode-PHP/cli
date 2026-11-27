# ADR-007 — Quality Tooling Delegated to KaririCode\Devkit

**Status:** Superseded by ADR-010 (4.1.0)
**Date:** 2026-09-29
**Component:** KaririCode\Cli V4.1

---


> Superseded: Spec V4.3.3 §29.1 and §32 require the gates, the lock, the SBOM
> and the pinned CI inside the component repository. ADR-010 records the
> replacement; this record stays for history.

## Context

Spec V4.0 §13.2 lists `phpunit.xml.dist`, `phpstan.neon` and `psalm.xml` at the component root. Maintained per component, that means five dev dependencies and five near-identical config files repeated across 35+ packages, so one rule change becomes 35 pull requests.

KaririCode\Devkit addresses this. `kcode init` generates every tool config inside `.kcode/` (gitignored) from `composer.json`, and `kcode migrate` removes root-level configs and redundant `require-dev` entries.

## Decision

- `require-dev` contains only `kariricode/devkit`.
- No tool configuration files live at the repository root; `phpunit.xml.dist` added during the 4.1 review was removed.
- `.gitignore` ignores `/.kcode/`, `/vendor/` and `/composer.lock` (library).
- Quality gates run through the Devkit: `vendor/bin/kcode init && vendor/bin/kcode quality`.

### Alignment

Spec V4.0 principle "Integration without duplication": the component reuses the ecosystem's tooling contract instead of carrying its own copy.

## Consequences

**Positive:**
- Tooling is identical to every other V4 component; upgrading the Devkit upgrades all rules at once.

**Negative:**
- CI must run `kcode init` before any quality step.
- The `tests/Conformance` suite must be picked up by the Devkit-generated PHPUnit config; this has to be verified on the first CI run.

## Alternatives Considered

- **Root-level configs as listed in §13.2** — rejected: config drift across components is exactly what the Devkit was built to remove, and `kcode migrate` would delete them anyway.
- **Composer scripts wrapping each tool** — rejected: still needs every tool in `require-dev`.

## References

- KaririCode\Devkit — ADR-005 (`.kcode/` directory convention) and README ("Migration from Root-Level Configs").
- KaririCode Specification V4.0 §13.2 (project structure).
