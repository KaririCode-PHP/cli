# SPEC-001 — KaririCode\Cli

**Version:** 4.1.0 · **Spec:** V4.3.3 · **ARFA:** 1.3, level 1 · **ASVS:** 5.0, level 1
**Status:** Implemented; release gates pending (QD-V433-COMPLIANCE)

## 1. Purpose

`kcli` is the developer toolchain of the ecosystem: it scaffolds Spec-conformant
code (`make:*`), inspects a running application through ports (`inspect:*`) and
checks source trees and installed versions (`check:*`). It never runs in a
request path.

## 2. Dependencies and boundaries (§31, ADR-011)

| Package | Constraint | Role |
|---|---|---|
| `kariricode/contract` | ^2.11 | Exception families (ADR-009); `DI\ServiceProvider`, `DI\BindableContainer` |
| `kariricode/console` | ^4.1 | Host framework: commands implement its `Command` contract (documented §31 exception) |
| `ext-json`, `ext-mbstring` | * | JSON output; display-width-aware tables |

Optional capabilities arrive through CLI-owned ports, implemented by the
application or an adapter package and bound in its container:

| Port | Used by | Without it |
|---|---|---|
| `BindingSource` → `iterable<BindingEntry>` | `inspect:container` | status line, exit 0 |
| `RouteSource` → `iterable<RouteEntry>` | `inspect:router` | status line, exit 0 |
| `SyntaxTreeDumper::dump(file, format, withPositions)` | `inspect:ast` | exit 1 |
| `DiagnosticsSource::diagnose(path, minimumSeverity, maxFiles)` | `inspect:diagnostics` | exit 1 |
| `ComplianceEngine` (`name`, `scan(directory)`) | `check:arfa` | `RegexComplianceEngine` |

## 3. Commands

| Command | Writes | Exit codes |
|---|---|---|
| `make:dpo`, `make:entity`, `make:value-object` | PHP files (`--fields` inferred) | 0 done or dry run; 1 any contract exception |
| `make:command`, `make:repository`, `make:service`, `make:grammar`, `make:visitor` | PHP files | 0; 1 |
| `inspect:container`, `inspect:router` | Output only | 0 |
| `inspect:ast` | Output only | 0; 1 no adapter or parse failure; 2 unreadable file |
| `inspect:diagnostics` | Output only | 0; 1 error diagnostics, no adapter or adapter failure; 2 bad path or severity |
| `check:arfa` | Output only | 0 compliant or P1 only; 1 P0 found or bad `--path`; 2 P1 found with `--fail-on-p1` |
| `check:ecosystem` | Output only | 0 all at minimum; 1 outdated package or unreadable lock |

`make:*` run through `ScaffoldRunner`; `FileWriter` checks the whole batch
before the first write and never overwrites without `--force` (ADR-005, ADR-008).

## 4. Generated artifacts

| Target | Shape | Notes |
|---|---|---|
| DPO | `final readonly class` | `#[Validate]` / `#[Sanitize]` inferred per field (ADR-002) |
| Entity | `final class`, `public readonly $id`, `public private(set)` state | named constructor `create()` |
| Value Object | `final readonly class`, `of()` with guards | guards throw `\InvalidArgumentException` (V16, ADR-012) |
| Command | `final class` implementing Console `Command` | `#[AsCommand]` |
| Repository | `interface` + `final class` adapter | aggregate-typed parameters; adapter stubs throw until implemented (V16) |
| Service, Grammar, Visitor | `final class` | one type per file |

Every generated type carries the five documentation elements of §17.

## 5. Error model (ADR-009)

| Code | Family | Factory |
|---|---|---|
| 8600 | Validation | `InvalidScaffoldRequestException::invalidClassName()` |
| 8601 | Validation | `InvalidScaffoldRequestException::invalidNamespace()` |
| 8602 | Validation | `InvalidScaffoldRequestException::invalidFieldDefinition()` |
| 8620 | NotFound | `GeneratorNotFoundException::forTarget(string $target)` |
| 8640 | Runtime | `ScaffoldFailureException::fileExists(string $path)` |
| 8641 | Runtime | `ScaffoldFailureException::writeFailed(string $path, string $reason)` |
| 8642 | Runtime | `ScaffoldFailureException::registrySealed(string $target)` |

Messages start with `[CLI_86xx]`; validation messages never contain the input.

## 6. Internal taxonomy

| Kind | Types |
|---|---|
| Value Objects (`final readonly`) | `GeneratorContext`, `FieldDescriptor`, `GeneratedFile`, `InspectionResult`, `WriteOutcome`, `BindingEntry`, `RouteEntry` |
| Pure enums (E-003) | `GeneratorTarget`, `FieldType`, `OutputFormat`, `ViolationSeverity`, `WriteStatus` |
| Services (`final`, stateless) | 8 generators, `FieldInferenceEngine`, `FileWriter`, `ScaffoldRunner`, `OutputRenderer`, inspectors, `RegexComplianceEngine` |
| Two-phase service | `GeneratorRegistry` (sealed on first `get()`, ADR-003) |
| Ports | `Generator`, `Inspector` and the five ports of §2 |

## 7. `check:arfa` rules (regex engine, ADR-004)

| Code | Severity | Rule | Source |
|---|---|---|---|
| V01 | P0 | `declare(strict_types=1)` is the first statement | strict typing |
| V03 | P1 | backed enum needs a documented external contract | E-003 |
| V04a / V04b | P0 | `toArray()` / `fromArray()` on a domain type | E-001 |
| V07 | P1 | `jsonSerialize()` on a domain type | E-001 |
| V09 | P1 | more than one class per file | one type per file |
| V10 | P1 | `@package`, `@author`, `@license`, `@since`, `@copyright`, `@category`, `@version` | §17 |
| V11 | P1 | untyped class constant | typed constants |
| V12 | P1 | regex ends with `$` instead of `\z` (no `m` flag) | anchors |
| V13 | P1 | parameterless `getX()` method | properties over getters |
| V14 | P0 | property hook in a `readonly` class | E-040 |
| V15 | P1 | type lacks `Preconditions:`, `Thread-safety:`, `Side effects:`, `Example:` or `References:` | §17 |
| V16 | P1 | `throw new` of a generic SPL exception | contract families |
| V17 | P1 | `// ──` section marker that is not 72 columns | style |
| V18 | P1 | `object`-typed parameter | §31 |

V02, V05, V06 and V08 are reserved for AST engines. Rows carry `code`,
`severity`, `file`, `line` and `message`; the summary carries `files_scanned`,
`p0_violations`, `p1_violations`, `total`, `status` and `engine`.

## 8. Quality gates (§13, §29.1, §32; ADR-010)

`composer check` runs `cs-check`, `phpstan` (max), `psalm` (level 1),
`complexity` (cyclomatic ≤ 10, nesting ≤ 3, parameters ≤ 4, cognitive ≤ 15),
`test` and `security`; CI adds `infection` (MSI ≥ 80) and `sbom`
(CycloneDX 1.5). Conformance tests (`tests/Conformance`) check the rules of
§7 on the CLI itself, the error model, G4 metadata, the supply chain files and
the documentation layout.

## 9. ARFA 1.3 mapping

| Principle | Where |
|---|---|
| P1 Immutable state | Value Objects; registry sealed after first use |
| P3 Adaptive context | `FieldInferenceEngine` infers processors from names and types |
| P4 Protocol agnosticism | generators produce strings only; I/O in `FileWriter`; `InspectionResult` is rendered by `OutputRenderer` in any format; capabilities reach the CLI through ports |

## 10. Known deviations

| Item | Rule | Status |
|---|---|---|
| `InspectionResult::$rows` / `$summary` are array shapes | §18 | Accepted: renderer input, not a domain type |
| `Generator::generate()` returns `list<GeneratedFile>` | §18 | Accepted: typed list |
| `kariricode/console` required | §31 | Documented exception (ADR-011) |
| Generated VO guards and repository stubs throw SPL exceptions | V16 | Intended P1 for the application to replace (ADR-012) |
| Five complexity breaches | §13 | Baselined with reasons in `tools/complexity-baseline.php` |
