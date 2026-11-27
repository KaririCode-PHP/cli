# ADR-011 — Component Boundaries: Contract, Console Host and CLI-Owned Ports

**Status:** Accepted
**Date:** 2026-10-01

## Context

4.0 typed the container and the router as `object` and read them by
reflection, and reached `kariricode/parser` through `class_exists()` and class
names. Spec V4.3.3 §31 routes every coupling between components through
`kariricode/contract` (PSRs excepted) and rules out duck typing with
`object`.

## Decision

- **Contract.** `kariricode/contract` ^2.11 supplies the exception families
  (ADR-009) and `DI\ServiceProvider`/`DI\BindableContainer`.
  `CliServiceProvider` implements `ServiceProvider`; `register()` only binds,
  and its factories receive the container.
- **Console host.** The CLI is a Console application: its commands implement
  `KaririCode\Console\Contract\Command`. Those contracts are not in
  `kariricode/contract` 2.11.0, so `kariricode/console` stays a required
  dependency. This is the one documented exception to §31 until the command
  contracts move into the contract package.
- **CLI-owned ports.** Capabilities that only the application or an adapter
  can provide are interfaces in `KaririCode\Cli\Contract`, as the contract
  package does for ports applications implement: `BindingSource`,
  `RouteSource` (with the `BindingEntry` and `RouteEntry` Value Objects),
  `SyntaxTreeDumper`, `DiagnosticsSource` and `ComplianceEngine`. No CLI file
  names a class of `kariricode/parser`, `kariricode/router` or
  `kariricode/dependency-injection`.
- **Without an adapter.** `inspect:container` and `inspect:router` print a
  status line; `inspect:ast` and `inspect:diagnostics` exit 1;
  `check:arfa` uses `RegexComplianceEngine`.

## Consequences

- No reflection and no `object` parameters; fakes test every port.
- A Parser API change cannot break the CLI; an adapter absorbs it.
- `inspect:ast` and `inspect:diagnostics` do nothing until an adapter exists;
  none is published yet.

## Alternatives Considered

- **PSR-11 for bindings.** Rejected: PSR-11 cannot list entries.
- **Depend on `kariricode/parser` directly.** Rejected: §31, and its V4 API
  is unverified.
- **Move the ports into `kariricode/contract`.** Deferred: only the CLI uses
  them; revisit when a second consumer appears.

## References

- Spec V4.3.3 §31; `kariricode/contract` 2.11.0 ADR-005.
- ADR-004 (amendment), ADR-009.
