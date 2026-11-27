# ADR-013 — Quality Gate through KaririCode Devkit, with the Root Configs

**Status:** Accepted; amends ADR-010 (where the tools come from)
**Date:** 2026-11-27

## Context

ADR-010 put the Spec V4.3.3 §29.1 configs in the repository and expected the
tools from `require-dev`. The ecosystem now installs its toolchain with
KaririCode Devkit: kcode 1.2.1 runs a component's committed configs when
`devkit.php` sets `'root_configs' => true`, so the gates that run are the
ones in the repository. kariricode/contract, exception, console 4.6 and
database 4.4 already work this way. The reviewed 4.1.0 still declared
`require-dev`, and its lock could not be produced (ADR-010, Consequences).

## Decision

- No `require-dev`. `devkit.php` pins PHPUnit 11.5.56, PHPStan 2.3.7 with
  tomasvotruba/cognitive-complexity 1.2.0, Psalm 6.19.1, PHP-CS-Fixer
  3.95.27 and Infection 0.35.5; kcode installs them into `.kcode/vendor/`.
  `.kcode/` is git-ignored and `devkit.php` is export-ignored.
- The root configs of ADR-010 stay and are what kcode runs; their paths into
  `vendor/` now point into `.kcode/vendor/` (`phpstan-complexity.neon`,
  `psalm.xml`, `infection.json.dist`, `phpunit.xml.dist`).
- The Composer scripts of ADR-010 keep their names: `composer check`,
  `composer infection`, `composer sbom`. `composer security` also audits the
  toolchain lock (`--working-dir=.kcode`). Infection 0.35 removed
  `--only-covered`; the script uses `--only-covering-test-cases`, and
  uncovered mutants stay out of the covered MSI, as before.
- `composer.lock` is committed with the runtime only: kariricode/console
  4.6.0, kariricode/contract 2.12.1, psr/event-dispatcher 1.0.0 and
  psr/log 3.0.2.
- CI on Linux installs kcode 1.2.1 by version and SHA-256 and runs
  `composer check`, `composer infection` and `composer sbom`. The Windows
  job installs the same PHPUnit with `composer global require` and runs the
  Unit suite, which covers the path handling of the scaffold pipeline
  (drive letters, separators). kcode has not been verified on Windows, and
  the Conformance suite reads the code, which Linux covers.

## Consequences

- One toolchain model across the ecosystem; the gates of ADR-010 are
  unchanged.
- `composer install` alone no longer brings the tools: contributors install
  kcode once (README, Quality).
- The Windows job checks behaviour, not style or static analysis.
- The blocker in ADR-010's consequences is gone: contract 2.12 and
  console 4.6 are tagged and the lock resolves.

## Alternatives Considered

- **Keep the tools in `require-dev`.** Rejected: the toolchain's dependencies
  would enter the component's lock and audit surface, and each component
  would drift to its own tool versions.
- **Devkit-generated configs (ADR-007).** Rejected for the reason ADR-010
  gives: the committed configs are what the spec and the conformance tests
  read.
- **Global tools on every CI runner.** Rejected on Linux: the versions would
  come from the runner instead of `devkit.php`. Used only on Windows, for
  PHPUnit, pinned to the version `devkit.php` names.

## References

- Spec V4.3.3 §29.1, §32; ADR-007 (superseded), ADR-010.
- kariricode/console ADR-015 (the same move).
- https://infection.github.io/guide/command-line-options.html
