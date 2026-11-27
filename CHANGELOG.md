# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [4.1.0] — 2026-11-27

First published release of the V4.1 line. It was reviewed against ARFA 1.3 / Spec V4.0, migrated to Spec V4.3.3, and before publication adapted to kariricode/console 4.6 and to the kcode toolchain. Gates on 2026-11-27: 149 tests, PHPStan max and Psalm level 1 without errors, MSI 85% (docs/quality/QD-V433-COMPLIANCE.md).

### Added

- Spec V4.3.3 conformance suite `tests/Conformance/SpecConformanceTest.php`: documentation elements of each type's own docblock, markers, final classes, typed constants, `\z` anchors, no getters or `object` parameters, one contract family per exception, unique codes in 8900–8999, E-047, reviewed catches, G4 metadata, §32 files and the documentation layout.
- CLI-owned ports (ADR-011): `BindingSource`, `RouteSource`, `SyntaxTreeDumper`, `DiagnosticsSource`, `ComplianceEngine`; Value Objects `BindingEntry` and `RouteEntry`.
- `RegexComplianceEngine` with rules V10–V18: §17 documentation, typed constants, `\z`, getters, hooks in readonly classes (E-040), generic exceptions, 72-column markers, `object` parameters.
- Quality gates in the repository (ADR-010), run through kcode (ADR-013): the §29.1 Composer scripts, PHPStan max, Psalm level 1, PER-CS 3.0, Infection >= 80, `tools/complexity.php` with a justified baseline, `tools/secret-scan.php`, `tools/sbom.php` (CycloneDX 1.5) and CI pinned by commit SHA.
- `Application\InputValue`: reads console 4.6's `mixed` arguments and options as the scalar a command needs, and falls back to the default for a value of another shape.
- `make:service` command (`ServiceGenerator` was registered but unreachable).
- `ScaffoldRunner`, the shared generate → write → report pipeline for all `make:*` commands (ADR-008).
- Pure enums `WriteStatus` and `ViolationSeverity`.
- `invalidNamespace` (8901) and `registrySealed` (8942) errors.
- Tests: every command against the console 4.6 contract and through a real `Application`, including the exit codes and `check:ecosystem` against sample locks; golden outputs of `OutputRenderer`, `RegexComplianceEngine` and `DpoGenerator`; `GeneratorContext`, `FileWriter`, `ScaffoldRunner`, `ViolationSeverity`, every generator, and `tests/Conformance/SelfComplianceTest`.
- ADR-006, ADR-008 and ADR-009 to ADR-013; ADR-007, superseded by ADR-010 before release; amendments to ADR-001 (console 4.6) and ADR-009 (block 8900–8999); Alternatives Considered and References sections in ADR-001 to ADR-005. `docs/quality/QD-V433-COMPLIANCE.md`, `docs/security/SECURITY.md` and CONTRIBUTING.md.

### Changed

- **Breaking.** Requires `kariricode/console` ^4.6 and `kariricode/contract` ^2.12. Commands declare `$name` and `$description` and return `ExitCode`; `check:arfa --fail-on-p1` keeps status 2 through `ExitCode::Invalid` (ADR-001, amendment).
- **Breaking.** `CliException` (9200–9300) is replaced by `InvalidScaffoldRequestException` (Validation, 8900–8902), `GeneratorNotFoundException` (NotFound, 8920) and `ScaffoldFailureException` (Runtime, 8940–8942); messages start with `[CLI_89xx]` (ADR-009). Commands catch `KaririCode\Contract\Exception\ExceptionInterface`. The reviewed code used 8600–8699, which kariricode/cache 4.1.0 released first (ADR-009, amendment).
- **Breaking.** `Generator::getType()` and `Inspector::getComponentName()` are now the interface properties `$type` and `$componentName`.
- **Breaking.** `ContainerInspector` and `RouterInspector` take ports instead of `object`; `inspect:ast` and `inspect:diagnostics` use adapters instead of `kariricode/parser` classes; `CliServiceProvider` implements `DI\ServiceProvider` from `kariricode/contract` and builds the application through Console's `Provider\ConsoleServiceProvider`.
- `make:command` generates a console 4.6 command: `$name` and `$description` properties, `#[\Override]`, `ExitCode`.
- `check:ecosystem` compares the lock with the releases of the Spec V4.3.3 line on Packagist: contract 2.12.0, exception 2.0.0, class-discovery 2.0.0, property-inspector 3.0.0, processor-pipeline 3.0.0, cache 4.1.0, console 4.6.0, router 4.1.0, database 4.4.0. A package joins the list when its release for the line is published.
- Every type documents its role, ARFA principle and Spec classification, `Preconditions:`, `Thread-safety:`, `Side effects:`, `Example:` and `References:` with `@see`; generated code does the same (ADR-012).
- `composer.json`: `php` `>=8.4`, `ext-json`, `ext-mbstring`, `suggest` for kariricode/parser, kariricode/dependency-injection and kariricode/router, and `extra.kariricode` {arfa 1.3/1, asvs 5.0/1, spec 4.3.3} replaces `extra.arfa`.
- Toolchain from KaririCode Devkit (ADR-013): no `require-dev`; `devkit.php` pins PHPUnit 11.5.56, PHPStan 2.3.7, Psalm 6.19.1, PHP-CS-Fixer 3.95.27 and Infection 0.35.5; `composer.lock` is committed with the runtime only; `composer security` also audits the toolchain; CI installs kcode 1.2.1 by version and SHA-256.
- Typed class constants; regexes end with `\z`; `CheckEcosystemCommand::execute()` split to meet the complexity budget.
- Binary renamed from `kcode` to `kcli`, avoiding the collision with kcode, the KaririCode Devkit binary (ADR-006).
- `make:entity` output follows the Spec type taxonomy: `public readonly` id, `public private(set)` state, no getters.
- `check:arfa`: P1-only results exit 0 unless `--fail-on-p1` is set (then 2); JSON and plain formats print no banner; `--path` must be a directory.
- Class names are normalised (`user` → `User`) before validation, and absolute `--path` values are honoured.
- Tests use PHPUnit attributes (`#[CoversClass]`, `#[DataProvider]`) instead of doc-comment annotations.
- README describes what the package does; the comparison with Laravel Artisan, which had inaccurate claims, is gone.

### Fixed

- Under console 4.6, `bin/kcli` stopped with a fatal error on the first command it loaded: the commands had no `$name`/`$description` and returned `int`.
- `make:command` generated a command that console 4.6 rejects.
- `make:grammar` and `make:visitor` generated classes that did not implement the kariricode/parser interfaces (other methods, and `GrammarRegistry` from a namespace that does not exist), so loading them was a fatal error. They now implement `Grammar::id()`/`phpVersionRange()` and `NodeVisitor::enterNode()`/`leaveNode()` returning `?Node`, as kariricode/parser 4.1 declares them.
- `check:ecosystem` reported every up-to-date project as OUTDATED: its minimums were versions that were never released (contract 4.0.0, processor-pipeline 4.0.0, class-discovery 3.2.0). Its table also padded multibyte cells by bytes, which shifted the MINIMUM column.
- Option and argument values were cast from `mixed`, so an array value became `"Array"`.
- The eight generator classes had no §17 elements of their own; the conformance test searched the whole file and found them in the templates. It now reads the type's docblock.
- `tools/secret-scan.php` tried to read a symlinked directory and scanned `.kcode/`.
- On Windows, `check:arfa` reported nested files with backslashes; reports use forward slashes on every platform.
- V01 flagged every compliant file: `\s*(?!declare…)` backtracked. The rule now uses possessive quantifiers and skips shebangs and comments.
- The `check:arfa --severity` filter was inverted, and the default hid all P0 rows.
- JSON output of `inspect:*` and `check:arfa` was wrapped in header, footer and banner text.
- `check:ecosystem`: wrong `processor-pipeline` package name, `packages-dev` ignored, `dev-*` versions reported as OUTDATED, and a non-standard `<e>` tag.
- Only `make:dpo` validated class names, and no `make:*` command handled `CliException`.
- `make:repository` could leave a half-generated pair; a batch pre-flight now prevents it.
- A sealed registry raised `generatorNotFound` instead of a dedicated code.
- Table columns misaligned with multibyte cells (`—`, `✓`) in `OutputRenderer`.
- `inspect:ast` accepted directories as files.
- Initial review: generator output indentation (DPO, Entity, ValueObject, Grammar/Visitor), `<e>` output tags, `FileWriter` CREATED/UPDATED detection, the missing `GeneratorContext::$date`, and Grammar/Visitor command wiring.

### Removed

- Durations (`InspectionResult::$durationMs`, the "Completed in" footer, JSON `duration_ms`) and the generated `@author`/`@since` lines, so no ambient clock remains (§19.2). `GeneratorContext` lost `author`, `date` and `extraOption`.
- Unused error codes 9250 and 9300 and the `class_exists()` probe of `kariricode/parser`.
- Java-style accessors in generated entities.
