# QD-V433-COMPLIANCE — KaririCode\Cli 4.1.0

**Spec:** V4.3.3 · **ARFA:** 1.3, level 1 · **ASVS:** 5.0, level 1
**Status:** Measured. All gates pass against the real packages
(kariricode/console 4.6.0, kariricode/contract 2.12.1); §2 lists what stays
open after the release.
**Measured:** 2026-11-27 with PHP 8.4.28, PHPUnit 11.5.56, PHPStan 2.3.7,
Psalm 6.19.1, PHP-CS-Fixer 3.95.27 and Infection 0.35.5, all pinned in
`devkit.php` and installed by kcode (ADR-013).

## 1. Gate results

| Gate | Command | Result |
|---|---|---|
| Syntax | `php -l` on src, tests, tools, bin | 82 files, 0 errors |
| Style | `composer cs-check` (PER-CS 3.0) | pass: 0 files to fix |
| Static analysis | `composer phpstan` (level max, src and tests) | pass: 0 errors |
| Static analysis | `composer psalm` (errorLevel 1) | pass: 0 errors |
| Complexity | `composer complexity` | pass: 173 functions within budget, 5 baselined (§4); cognitive complexity 0 errors |
| Unit | `composer test` (suite Unit) | 136 tests, 361 assertions, all pass |
| Conformance | `composer test-conformance` | 13 tests, 894 assertions, all pass |
| Security | `composer security` | `composer audit --locked`: 0 advisories (runtime and toolchain); secret scan: no findings; group security: 2 tests, 14 assertions, all pass |
| Mutation | `composer infection` (MSI and covered MSI ≥ 80) | pass: MSI 85%, covered MSI 85% (733 mutants: 628 killed, 105 escaped) |
| SBOM | `composer sbom` | CycloneDX 1.5, 4 components: kariricode/console 4.6.0, kariricode/contract 2.12.1, psr/event-dispatcher 1.0.0, psr/log 3.0.2 |
| Self-check | `kcli check:arfa --path=src` | 55 files, 0 violations |
| Generated code | the 8 generators through `kcli make:*`, scanned with `check:arfa` | 9 files: 0 P0, 4 P1, all V16 in the repository stubs and intended (ADR-012); all 9 pass `php -l` and load; the grammar and the visitor load against the kariricode/parser 4.1 interfaces |
| Binary | `bin/kcli list`, `kcli check:ecosystem` on this repository | the 14 commands load under console 4.6; exit 0 |

## 2. Open items after the release

1. Publish adapters: `SyntaxTreeDumper`, `DiagnosticsSource` and an AST
   `ComplianceEngine` (kariricode/parser); `BindingSource` and `RouteSource`
   (dependency injection and router). None exists yet (ADR-011).
2. kariricode/parser is not published. `make:grammar` and `make:visitor`
   follow the interfaces of its 4.1 line; if its publication changes them,
   the generators change with it.
3. `check:ecosystem` lists only the packages whose release for the Spec
   V4.3.3 line is on Packagist; dependency-injection, event-dispatcher,
   logging, parser, sanitizer and validator join when they are published.
4. The `v4.1.0` tag is created by the GitHub release and is not signed;
   signing needs the maintainer's key (§32, CONTRIBUTING).
5. Windows CI runs the Unit suite with the PHPUnit that `devkit.php` pins;
   kcode itself is not verified on Windows (ADR-013).

## 3. Rules and evidence

| Rule | Evidence |
|---|---|
| §17 documentation elements in each type's own docblock; no `@package`, `@author`, `@license`, `@since` | V10, V15; `testDocblocksCarryTheFiveElementsAndNoBoilerplateTags` |
| 72-column section markers | V17; `testSectionMarkersAreSeventyTwoColumns` |
| Concrete classes final; typed constants | V11; `testConcreteClassesAreFinalAndConstantsTyped` |
| `\z` anchors; no getters; no `object` parameters (§31) | V12, V13, V18; `testRegexesAnchorWithBackslashZAndNoGettersOrObjectParameters` |
| One contract family per exception; private constructor | `testEachExceptionImplementsExactlyOneContractFamily` |
| Codes unique inside 8900–8999 with matching prefix | `testErrorCodesAreUniqueAndInsideTheCliBlock` |
| E-047 | `testValidationMessagesNeverEchoTheRejectedValue` (group security) |
| Reviewed catches only | `testEveryCatchInSourceIsReviewed` (group security) |
| G4 metadata | `testComposerMetadataFollowsGateG4` |
| §32 files, SHA-pinned actions, lock not ignored | `testSupplyChainFollowsSection32`; kcode pinned by version and SHA-256 in CI |
| Console 4.6 command contract | `ConsoleContractTest`: `$name`/`$description` match `#[AsCommand]`, `execute()` returns `ExitCode` |
| Canonical documentation layout | `testDocumentationFollowsTheCanonicalLayout` |
| E-040 | V14 on every tree `check:arfa` scans |
| §19.2 injectable time and randomness | no clock or random call in src (`date`, `time`, `hrtime`, `microtime`, `random_*`, `new DateTime`): 0 occurrences |
| §13 budgets | `tools/complexity.php` (§1) |

## 4. Accepted deviations

| Item | Reason |
|---|---|
| `GeneratorContext::__construct`, `FieldDescriptor::__construct`: 7 parameters | DPOs carrying parsed CLI input; named arguments at every call site |
| `FieldType::fromString`, `GeneratorTarget::label`, `GeneratorTarget::defaultSubDirectory`: cyclomatic 14 | exhaustive `match` over the enum's own cases, one expression per arm |
| `InspectionResult` rows and summary are array shapes (§18) | renderer input, not a domain type |
| `kariricode/console` is required (§31) | host framework; its command contracts are not in `kariricode/contract` (ADR-011) |
| Generated VO guards and repository stubs throw SPL exceptions (V16) | intended P1 placeholders for the application to replace (ADR-012) |
