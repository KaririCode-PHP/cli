# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [4.1.0] — Unreleased

First release of the V4.1 line: reviewed against ARFA 1.3 / Spec V4.0, then migrated to Spec V4.3.3.

### Added

- Spec V4.3.3 conformance suite `tests/Conformance/SpecConformanceTest.php`: documentation elements, markers, final classes, typed constants, `\z` anchors, no getters or `object` parameters, one contract family per exception, unique codes in 8600–8699, E-047, reviewed catches, G4 metadata, §32 files and the documentation layout.
- CLI-owned ports (ADR-011): `BindingSource`, `RouteSource`, `SyntaxTreeDumper`, `DiagnosticsSource`, `ComplianceEngine`; Value Objects `BindingEntry` and `RouteEntry`.
- `RegexComplianceEngine` with rules V10–V18: §17 documentation, typed constants, `\z`, getters, hooks in readonly classes (E-040), generic exceptions, 72-column markers, `object` parameters.
- Quality gates in the repository (ADR-010): the §29.1 Composer scripts, PHPStan max, Psalm level 1, PER-CS 3.0, Infection >= 80, `tools/complexity.php` with a justified baseline, `tools/secret-scan.php`, `tools/sbom.php` (CycloneDX 1.5) and CI pinned by commit SHA.
- ADR-009 to ADR-012, `docs/quality/QD-V433-COMPLIANCE.md`, `docs/security/SECURITY.md` and CONTRIBUTING.md.- `make:service` command (`ServiceGenerator` was registered but unreachable).
- `ScaffoldRunner`, the shared generate → write → report pipeline for all `make:*` commands (ADR-008).
- Pure enums `WriteStatus` and `ViolationSeverity`.
- Error codes 9205 (`invalidNamespace`) and 9206 (`registrySealed`).
- Tests for `GeneratorContext`, `FileWriter`, `ScaffoldRunner`, `OutputRenderer`, `ViolationSeverity`, the Entity/ValueObject/Parser generators, and `tests/Conformance/SelfComplianceTest`.
- ADR-006, ADR-007 and ADR-008; Alternatives Considered and References sections in ADR-001 to ADR-005. Superseded by ADR-010 before release.

### Changed

- **Breaking.** `CliException` (9200–9300) is replaced by `InvalidScaffoldRequestException` (Validation, 8600–8602), `GeneratorNotFoundException` (NotFound, 8620) and `ScaffoldFailureException` (Runtime, 8640–8642); messages start with `[CLI_86xx]` (ADR-009). Commands catch `KaririCode\Contract\Exception\ExceptionInterface`.
- **Breaking.** `Generator::getType()` and `Inspector::getComponentName()` are now the interface properties `$type` and `$componentName`.
- **Breaking.** `ContainerInspector` and `RouterInspector` take ports instead of `object`; `inspect:ast` and `inspect:diagnostics` use adapters instead of `kariricode/parser` classes; `CliServiceProvider` implements `DI\ServiceProvider` from `kariricode/contract` and builds the application through Console's `Provider\ConsoleServiceProvider`.
- Every type documents its role, ARFA principle and Spec classification, `Preconditions:`, `Thread-safety:`, `Side effects:`, `Example:` and `References:` with `@see`; generated code does the same (ADR-012).
- `composer.json`: `php` `>=8.4`, `kariricode/contract` ^2.11, and `extra.kariricode` {arfa 1.3/1, asvs 5.0/1, spec 4.3.3} replaces `extra.arfa`.
- Typed class constants; regexes end with `\z`; `CheckEcosystemCommand::execute()` split to meet the complexity budget.- Binary renamed from `kcode` to `kcli`, avoiding the `vendor/bin/kcode` collision with `kariricode/devkit` (ADR-006).
- `make:entity` output follows the Spec V4.0 type taxonomy: `public readonly` id, `public private(set)` state, no getters.
- `check:arfa`: P1-only results exit 0 unless `--fail-on-p1` is set (then 2); JSON and plain formats print no banner; `--path` must be a directory.
- `composer.json`: `php ^8.4`, `ext-mbstring`, `suggest` for `kariricode/parser`, and `require-dev` reduced to `kariricode/devkit`.
- Class names are normalised (`user` → `User`) before validation, and absolute `--path` values are honoured.
- Tests use PHPUnit attributes (`#[CoversClass]`, `#[DataProvider]`) instead of doc-comment annotations.

### Fixed
- V01 flagged every compliant file: `\s*(?!declare…)` backtracked. The rule now uses possessive quantifiers and skips shebangs and comments.
- The `check:arfa --severity` filter was inverted, and the default hid all P0 rows.
- JSON output of `inspect:*` and `check:arfa` was wrapped in header, footer and banner text.
- `check:ecosystem`: wrong `processor-pipeline` package name, `packages-dev` ignored, `dev-*` versions reported as OUTDATED, and a non-standard `<e>` tag.
- Only `make:dpo` validated class names, and no `make:*` command handled `CliException`.
- `make:repository` could leave a half-generated pair; a batch pre-flight now prevents it.
- A sealed registry raised `generatorNotFound` (9204) instead of a dedicated code.
- Table columns misaligned with multibyte cells (`—`, `✓`).
- `inspect:ast` accepted directories as files.
- Initial review: generator output indentation (DPO, Entity, ValueObject, Grammar/Visitor), `<e>` output tags, `FileWriter` CREATED/UPDATED detection, the missing `GeneratorContext::$date`, and Grammar/Visitor command wiring.

### Removed

- Durations (`InspectionResult::$durationMs`, the "Completed in" footer, JSON `duration_ms`) and the generated `@author`/`@since` lines, so no ambient clock remains (§19.2). `GeneratorContext` lost `author`, `date` and `extraOption`.
- Unused error codes 9250 and 9300 and the `class_exists()` probe of `kariricode/parser`.- Root `phpunit.xml.dist`; the Devkit generates it into `.kcode/` (ADR-007). Superseded by ADR-010 before release.
- Java-style accessors in generated entities.
