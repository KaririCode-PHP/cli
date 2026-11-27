# QD-V433-COMPLIANCE — KaririCode\Cli 4.1.0

**Spec:** V4.3.3 · **ARFA:** 1.3, level 1 · **ASVS:** 5.0, level 1
**Status:** PENDING. Code, tests and documentation conform; the release gates
in §2 are open.
**Measured:** 2026-10-01 with PHP 8.4.23 and PHPUnit 11.5.56.

## 1. Gate results

| Gate | Command | Result |
|---|---|---|
| Syntax | `php -l` on src, tests, tools, bin | 75 files, 0 errors |
| Unit | `phpunit --testsuite Unit` | 94 tests, 183 assertions, all pass |
| Conformance | `phpunit --testsuite Conformance` | 13 tests, 876 assertions, all pass |
| Security group | `phpunit --group security` | 2 tests, 14 assertions, all pass |
| Self-check | `RegexComplianceEngine` over `src` | 54 files, 0 violations |
| Generated code | output of all 8 generators scanned with the same rules | 9 files: 0 P0, 7 P1, all V16 and intended (ADR-012); all 9 pass `php -l` |
| Complexity | `php tools/complexity.php src` | 164 functions within budget; 5 baselined (§4) |
| Secret scan | `php tools/secret-scan.php` | no findings |
| SBOM | `php tools/sbom.php` | generator verified on a sample lock; this repository's lock is pending |
| PHPStan max, Psalm 1, PHP-CS-Fixer, Infection, cognitive complexity, `composer audit --locked` | `composer check`, `composer infection` | PENDING: need `kariricode/console` 4.x and `kariricode/contract` 2.11.0 installed |

The test runs used minimal fixtures for the `kariricode/contract` 2.11.0
interfaces (exception families, `DI`) and Console's `Output`, with the
signatures published in the contract's ADR-005. A run against the real
packages is part of item 2 below.

## 2. Open items before the release

1. Tag `kariricode/contract` 2.11.0 and make `kariricode/console` 4.x
   resolvable; run `composer update` and commit `composer.lock` (§32).
2. Run `composer check` and `composer infection` against the real packages and
   record the figures here.
3. First CI run on GitHub (Linux gate and Windows tests).
4. Register block 8600–8699, prefix `CLI`, in the Spec error-code registry.
5. Publish adapters: `SyntaxTreeDumper`, `DiagnosticsSource` and an AST
   `ComplianceEngine` (Parser V4); `BindingSource` and `RouteSource`
   (dependency injection and router). None exists yet (ADR-011).
6. Sign the `v4.1.0` tag.

## 3. Rules and evidence

| Rule | Evidence |
|---|---|
| §17 documentation elements; no `@package`, `@author`, `@license`, `@since` | V10, V15; `testDocblocksCarryTheFiveElementsAndNoBoilerplateTags` |
| 72-column section markers | V17; `testSectionMarkersAreSeventyTwoColumns` |
| Concrete classes final; typed constants | V11; `testConcreteClassesAreFinalAndConstantsTyped` |
| `\z` anchors; no getters; no `object` parameters (§31) | V12, V13, V18; `testRegexesAnchorWithBackslashZAndNoGettersOrObjectParameters` |
| One contract family per exception; private constructor | `testEachExceptionImplementsExactlyOneContractFamily` |
| Codes unique inside 8600–8699 with matching prefix | `testErrorCodesAreUniqueAndInsideTheCliBlock` |
| E-047 | `testValidationMessagesNeverEchoTheRejectedValue` (group security) |
| Reviewed catches only | `testEveryCatchInSourceIsReviewed` (group security) |
| G4 metadata | `testComposerMetadataFollowsGateG4` |
| §32 files, SHA-pinned actions, lock not ignored | `testSupplyChainFollowsSection32` |
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
