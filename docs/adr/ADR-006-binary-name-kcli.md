# ADR-006 — Binary Name `kcli`: Coexistence with KaririCode\Devkit

**Status:** Accepted
**Date:** 2026-09-29
**Component:** KaririCode\Cli V4.1

---

## Context

`kariricode/devkit` — the ecosystem's single `require-dev` for PHPUnit, PHPStan, Psalm, CS-Fixer and Rector — ships its binary as `bin/kcode` (renamed from `kdevkit` by explicit decision of the framework author). This component also declared `bin/kcode`.

A project that installs both packages gets two packages claiming `vendor/bin/kcode`. Composer links only one of them and skips the other with a "name conflicts with an existing file" warning, so one tool silently becomes unreachable. Both packages are dev tools and are expected to be installed together.

## Decision

The CLI binary is `bin/kcli`. The name follows the `k` + package pattern the author used for `kdevkit`, is short to type, and states what the binary is.

`vendor/bin/kcode` belongs to the Devkit (quality pipeline). `vendor/bin/kcli` belongs to this component (`make:*`, `inspect:*`, `check:*`).

### Alignment

ARFA 1.3 P4: the binary is a thin adapter; commands remain protocol-agnostic and callable through `CliServiceProvider::buildApplication()` regardless of the entry-point name.

## Consequences

**Positive:**
- Both tools install side by side with no Composer warning.
- Command namespaces stay unambiguous in docs and CI (`kcode quality` vs `kcli check:arfa`).

**Negative:**
- Scripts written against the unreleased draft (`kcode make:*`) must switch to `kcli`. No alias is shipped because 4.1.0 is the first release of this binary.

## Alternatives Considered

- **Rename the Devkit binary** — rejected: `kcode` for the Devkit was an explicit, recent decision and the Devkit docs and CI examples depend on it.
- **One binary for both** (the Devkit loads CLI commands as a plugin) — deferred: the Devkit has its own dependency-free `Application`, not `kariricode/console`, so hosting Console commands requires a bridge in the Devkit first.
- **Keep `kcode` and document the clash** — rejected: the failure mode is silent (whichever package installs first wins).

## References

- Composer documentation — "Vendor binaries and the `vendor/bin` directory" (getcomposer.org/doc/articles/vendor-binaries.md).
- KaririCode\Devkit — README and ADR-005 (`.kcode/` directory convention).
