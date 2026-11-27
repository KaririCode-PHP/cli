# KaririCode\Cli

[![PHP](https://img.shields.io/badge/PHP-8.4%2B-blue)](https://www.php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![ARFA](https://img.shields.io/badge/ARFA-1.3-orange)](https://kariricode.org/arfa)
[![Spec](https://img.shields.io/badge/Spec-V4.3.3-purple)](https://kariricode.org/spec)

`kcli` is the developer CLI of the KaririCode Framework. It scaffolds Spec
V4.3.3 types, inspects an application through ports it owns, and checks a
source tree against ARFA 1.3 / Spec V4.3.3 rules. Every command implements
`KaririCode\Console\Contract\Command` directly, with no base class
(ADR-001).

---

## What it provides

| Area | Commands | Notes |
|---|---|---|
| Code generation | `make:dpo`, `make:entity`, `make:value-object`, `make:repository`, `make:service`, `make:command`, `make:grammar`, `make:visitor` | `--dry-run` and `--force` on every `make:*`; DPO rules inferred from field names |
| Inspection | `inspect:container`, `inspect:router`, `inspect:ast`, `inspect:diagnostics` | read the application through adapters you bind (ADR-011) |
| Compliance | `check:arfa` | regex engine built in; AST engines plug in through `ComplianceEngine` |
| Ecosystem | `check:ecosystem` | compares `composer.lock` with the releases of the Spec V4.3.3 line |

Requirements: PHP 8.4 with `json` and `mbstring`, `kariricode/console` ^4.6
and `kariricode/contract` ^2.12. Generated DPOs name validation and
sanitization rules (`Cpf`, `Cnpj`, `ZipCode`, ...); kariricode/validator and
kariricode/sanitizer implement them, not this package.

---

## Installation

```bash
composer require kariricode/cli
```

The binary is `vendor/bin/kcli` (ADR-006). Or install it globally:

```bash
composer global require kariricode/cli
```

---

## Quick Start

```bash
# Generate a DPO with field inference
kcli make:dpo UserRegistration --fields=email:string,password:string,name:string,cpf:cpf

# Preview without writing
kcli make:dpo Order --fields=amount:money,status:string --dry-run

# Generate a Command
kcli make:command SendWelcomeEmail

# Generate an Entity
kcli make:entity Product --fields=name:string,price:money,sku:string

# Generate a Value Object
kcli make:value-object EmailAddress --fields=value:email

# Generate Repository interface + DB skeleton
kcli make:repository UserRepository

# Generate an application service
kcli make:service UserRegistration

# Inspect DI container bindings
kcli inspect:container

# Inspect routes as tree
kcli inspect:router --format=tree

# Scan source for ARFA 1.3 violations
kcli check:arfa --path=src

# Check installed KaririCode package versions
kcli check:ecosystem
```

---

## Code Generation

### `make:dpo` — Data Preparation Object

A DPO (Data Preparation Object) is a `final readonly` class whose fields carry the validation and sanitization rules they need. The rules are inferred from the field names: `email` gets `NotBlank` + `Email` and `Trim` + `Lowercase`. When the name has no rule, the type's rules apply: `document:cpf` gets `Cpf` and `Digits`.

```bash
kcli make:dpo UserRegistration \
    --fields=email:string,password:string,name:string,cpf:cpf,phone:phone \
    --namespace=App\\Domain\\Auth
```

**Generated output:**

```php
#[MapInput]
final readonly class UserRegistrationDpo
{
    public function __construct(
        #[Validate(['NotBlank', 'Email'])]
        #[Sanitize(['Trim', 'Lowercase'])]
        public string $email,

        #[Validate(['NotBlank', 'Length(min:8)'])]
        #[Sanitize(['Trim'])]
        public string $password,

        #[Validate(['NotBlank', 'Length(max:255)'])]
        #[Sanitize(['Trim', 'Capitalize'])]
        public string $name,

        #[Validate(['NotBlank', 'Cpf'])]
        #[Sanitize(['Trim', 'Digits'])]
        public string $cpf,

        #[Validate(['NotBlank', 'Phone'])]
        #[Sanitize(['Trim', 'Digits'])]
        public string $phone,
    ) {}
}
```

**Auto-inferred types:**

| Field name | Validators | Sanitizers |
|---|---|---|
| `email` | NotBlank, Email | Trim, Lowercase |
| `password` | NotBlank, Length(min:8) | Trim |
| `name` | NotBlank, Length(max:255) | Trim, Capitalize |
| `cpf` | NotBlank, Cpf | Trim, Digits |
| `cnpj` | NotBlank, Cnpj | Trim, Digits |
| `phone` | NotBlank, Phone | Trim, Digits |
| `slug` | NotBlank, Slug | Trim, Lowercase, Slugify |
| `url` | NotBlank, Url | Trim |
| `website` | Url | Trim |
| `zip_code` | NotBlank, ZipCode | Trim, Digits |
| `price` / `amount` | NotBlank, Positive | (none) |
| `uuid` | NotBlank, Uuid | Trim, Lowercase |

### `make:command` — Console 4.6 Command

```bash
kcli make:command SendWelcomeEmail --namespace=App\\Command
```

Generates `SendWelcomeEmailCommand`, which implements `KaririCode\Console\Contract\Command` directly: `$name` and `$description` as interface properties, `configure()` for arguments and options, and `execute()` returning `ExitCode`. The command name comes from the class: `send:welcome:email`.

### `make:entity` — Domain Entity

```bash
kcli make:entity User --fields=name:string,email:email,status:string
```

Generates a `final class` entity with a private constructor, a `create()` factory, a `public readonly` id with identity `equals()`, and state as `public private(set)` properties (PHP 8.4 asymmetric visibility, Spec V4.3.3 type taxonomy). No getters are generated.

### `make:value-object` — Final Readonly Value Object

```bash
kcli make:value-object Money --fields=amount:float,currency:string
```

Generates a `final readonly class` with a `self::of()` static factory that validates invariants before construction.

### `make:repository` — Repository Interface + Implementation

```bash
kcli make:repository UserRepository
```

Generates two files: `UserRepository` (interface) and `DbUserRepository` (skeleton implementation). Both targets are checked before anything is written, so the pair is created together or not at all.

### `make:service` — Application Service

```bash
kcli make:service UserRegistration
```

Generates `UserRegistrationService`, a `final class` with a constructor-injection placeholder for its repository.

### `make:grammar` and `make:visitor` — kariricode/parser extensions

```bash
kcli make:grammar Blade --namespace=App\\Parser
kcli make:visitor ImportCollector --namespace=App\\Parser
```

Generate classes for the contracts of kariricode/parser 4.1: `BladeGrammar`
implements `Grammar` with `id()` (`blade`) and `phpVersionRange()`;
`ImportCollectorVisitor` implements `NodeVisitor`, whose `enterNode()` and
`leaveNode()` return a replacement node or `null`.

---

## Inspection

### `inspect:container`

Displays all DI container bindings with their concrete types and singleton status.

```bash
kcli inspect:container --format=table
kcli inspect:container --format=json
kcli inspect:container --filter=Service
```

### `inspect:router`

Lists all registered routes with method, path, handler, and name.

```bash
kcli inspect:router --format=tree
kcli inspect:router --method=GET
kcli inspect:router --path=/api
```

---

### Adapters for `inspect:*`

The inspect commands read the application through ports the CLI owns
(ADR-011). Bind an implementation in the application container, or pass a
container to `CliServiceProvider::buildApplication($container)`:

| Port | Command | Without an adapter |
|---|---|---|
| `BindingSource` | `inspect:container` | status line |
| `RouteSource` | `inspect:router` | status line |
| `SyntaxTreeDumper` | `inspect:ast` | exit 1 |
| `DiagnosticsSource` | `inspect:diagnostics` | exit 1 |
| `ComplianceEngine` | `check:arfa` | built-in regex engine |

```bash
kcli inspect:ast src/User.php --show-positions          # 0 ok, 1 no adapter, 2 unreadable file
kcli inspect:diagnostics src --severity=warning --format=json
```

## Compliance Checking

### `check:arfa` — ARFA 1.3 / Spec V4.3.3 Scanner

Validates a source tree against ARFA 1.3 / Spec V4.3.3 and reports the result through exit codes for CI pipelines.

```bash
kcli check:arfa --path=src

kcli check:arfa --severity=P0          # list only build-breaking violations
kcli check:arfa --format=json | jq .   # pure JSON, verdict in the exit code

# Exit codes:
# 0 — compliant, or only P1 violations
# 1 — P0 violations (build-breaking) or --path is not a directory
# 2 — P1 violations with --fail-on-p1
```

**Detected violations:**

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

V02, V05, V06 and V08 are reserved for AST engines plugged in through the
`ComplianceEngine` port (ADR-004, ADR-011).

### `check:ecosystem`

Reads `composer.lock` (`packages` and `packages-dev`) from the working directory and compares each KaririCode package with the lowest release the Spec V4.3.3 / ARFA 1.3 line works with: contract 2.12.0, exception 2.0.0, class-discovery 2.0.0, property-inspector 3.0.0, processor-pipeline 3.0.0, cache 4.1.0, console 4.6.0, router 4.1.0 and database 4.4.0. A package joins the list when its release for the line is published. Branch installs are reported as `DEV`; exit 1 when a package is below its minimum or the lock is missing or invalid.

```bash
kcli check:ecosystem
kcli check:ecosystem --json
```

---

## Error Codes

Block 8900–8999; each exception implements one `kariricode/contract` family
and its message starts with `[CLI_89xx]` (ADR-009).

| Code | Family | Factory |
|---|---|---|
| 8900 | Validation | `InvalidScaffoldRequestException::invalidClassName()` |
| 8901 | Validation | `InvalidScaffoldRequestException::invalidNamespace()` |
| 8902 | Validation | `InvalidScaffoldRequestException::invalidFieldDefinition()` |
| 8920 | NotFound | `GeneratorNotFoundException::forTarget(string $target)` |
| 8940 | Runtime | `ScaffoldFailureException::fileExists(string $path)` |
| 8941 | Runtime | `ScaffoldFailureException::writeFailed(string $path, string $reason)` |
| 8942 | Runtime | `ScaffoldFailureException::registrySealed(string $target)` |


## Architecture

### ARFA 1.3 Compliance

**P1 — Immutable State Transformation:** All value objects (`GeneratorContext`, `GeneratedFile`, `FieldDescriptor`, `InspectionResult`, `WriteOutcome`) are `final readonly`. Generators never mutate inputs.

**P2 — Reactive Flow Composition:** The generation pipeline composes `FieldInferenceEngine → Generator → FileWriter` as pure function composition with no shared state.

**P3 — Adaptive Context Awareness:** `FieldInferenceEngine` adapts to the semantic meaning of field names, producing enriched `FieldDescriptor` objects without requiring explicit configuration.

**P4 — Protocol Agnosticism:** All commands implement `KaririCode\Console\Contract\Command`. They can be driven by `ArgvInput` (CLI), `ArrayInput` (tests), or any future transport without code changes.

### File Structure

```
cli/
├── bin/
│   └── kcli
├── src/
│   ├── Application/
│   │   ├── FileWriter.php
│   │   ├── InputValue.php
│   │   ├── ScaffoldRunner.php
│   │   └── WriteOutcome.php
│   ├── Command/
│   │   ├── Check/
│   │   │   ├── CheckArfaCommand.php
│   │   │   └── CheckEcosystemCommand.php
│   │   ├── Inspect/
│   │   │   ├── InspectAstCommand.php
│   │   │   ├── InspectContainerCommand.php
│   │   │   ├── InspectDiagnosticsCommand.php
│   │   │   └── InspectRouterCommand.php
│   │   └── Make/
│   │       ├── MakeCommandCommand.php
│   │       ├── MakeDpoCommand.php
│   │       ├── MakeEntityCommand.php
│   │       ├── MakeGrammarCommand.php
│   │       ├── MakeRepositoryCommand.php
│   │       ├── MakeServiceCommand.php
│   │       ├── MakeValueObjectCommand.php
│   │       └── MakeVisitorCommand.php
│   ├── Contract/
│   │   ├── BindingSource.php
│   │   ├── ComplianceEngine.php
│   │   ├── DiagnosticsSource.php
│   │   ├── Generator.php
│   │   ├── Inspector.php
│   │   ├── RouteSource.php
│   │   └── SyntaxTreeDumper.php
│   ├── Enum/
│   │   ├── FieldType.php
│   │   ├── GeneratorTarget.php
│   │   ├── OutputFormat.php
│   │   ├── ViolationSeverity.php
│   │   └── WriteStatus.php
│   ├── Exception/
│   │   ├── GeneratorNotFoundException.php
│   │   ├── InvalidScaffoldRequestException.php
│   │   └── ScaffoldFailureException.php
│   ├── Generator/
│   │   ├── CommandGenerator.php
│   │   ├── DpoGenerator.php
│   │   ├── EntityGenerator.php
│   │   ├── GeneratorRegistry.php
│   │   ├── GrammarGenerator.php
│   │   ├── RepositoryGenerator.php
│   │   ├── ServiceGenerator.php
│   │   ├── ValueObjectGenerator.php
│   │   └── VisitorGenerator.php
│   ├── Inference/
│   │   └── FieldInferenceEngine.php
│   ├── Inspector/
│   │   ├── ArfaComplianceInspector.php
│   │   ├── ContainerInspector.php
│   │   ├── RegexComplianceEngine.php
│   │   └── RouterInspector.php
│   ├── Integration/
│   │   └── CliServiceProvider.php
│   ├── Template/
│   │   └── OutputRenderer.php
│   └── ValueObject/
│       ├── BindingEntry.php
│       ├── FieldDescriptor.php
│       ├── GeneratedFile.php
│       ├── GeneratorContext.php
│       ├── InspectionResult.php
│       └── RouteEntry.php
├── tests/
│   ├── Support/
│   │   └── RecordingOutput.php
│   ├── Conformance/
│   │   ├── SelfComplianceTest.php
│   │   └── SpecConformanceTest.php
│   └── Unit/
│       ├── Application/
│       │   ├── FileWriterTest.php
│       │   └── ScaffoldRunnerTest.php
│       ├── Command/
│       │   ├── AdapterCommandsTest.php
│       │   ├── CommandsTest.php
│       │   └── ConsoleContractTest.php
│       ├── Enum/
│       │   └── ViolationSeverityTest.php
│       ├── Generator/
│       │   ├── DpoGeneratorGoldenTest.php
│       │   ├── DpoGeneratorTest.php
│       │   ├── EntityGeneratorTest.php
│       │   ├── GeneratorRegistryTest.php
│       │   ├── ParserGeneratorsTest.php
│       │   └── ValueObjectGeneratorTest.php
│       ├── Inference/
│       │   └── FieldInferenceEngineTest.php
│       ├── Inspector/
│       │   ├── ArfaComplianceInspectorTest.php
│       │   ├── RegexComplianceEngineGoldenTest.php
│       │   └── RegexComplianceEngineTest.php
│       ├── Template/
│       │   ├── OutputRendererGoldenTest.php
│       │   └── OutputRendererTest.php
│       └── ValueObject/
│           └── GeneratorContextTest.php
├── tools/
│   ├── complexity-baseline.php
│   ├── complexity.php
│   ├── sbom.php
│   └── secret-scan.php
├── docs/
│   ├── adr/
│   │   ├── ADR-001-interface-based-commands.md
│   │   ├── ADR-002-field-inference-engine.md
│   │   ├── ADR-003-generator-registry-auto-seal.md
│   │   ├── ADR-004-compliance-engines.md
│   │   ├── ADR-005-filesystem-isolation.md
│   │   ├── ADR-006-binary-name-kcli.md
│   │   ├── ADR-007-devkit-tooling.md
│   │   ├── ADR-008-scaffold-pipeline.md
│   │   ├── ADR-009-error-model.md
│   │   ├── ADR-010-spec-tooling-and-supply-chain.md
│   │   ├── ADR-011-component-boundaries.md
│   │   ├── ADR-012-documentation-and-generated-code.md
│   │   └── ADR-013-toolchain-through-kcode.md
│   ├── quality/
│   │   └── QD-V433-COMPLIANCE.md
│   ├── security/
│   │   └── SECURITY.md
│   ├── spec/
│   │   └── SPEC-001-cli.md
│   └── README.md
├── .github/
│   └── workflows/
│       └── ci.yml
├── composer.json
├── composer.lock
├── devkit.php
├── phpunit.xml.dist
├── phpstan.neon
├── phpstan-complexity.neon
├── psalm.xml
├── .php-cs-fixer.dist.php
├── infection.json.dist
├── CHANGELOG.md
├── CONTRIBUTING.md
└── README.md
```

---

## Development

The toolchain comes from KaririCode Devkit (ADR-013): `devkit.php` pins
PHPUnit, PHPStan, Psalm, PHP-CS-Fixer and Infection, kcode installs them into
`.kcode/`, and they run the configs committed here (ADR-010). Install kcode
once, then:

```bash
composer install
kcode init                 # installs the pinned tools into .kcode/
composer check             # cs-check, phpstan (max), psalm (level 1), complexity, test, security
composer test-conformance  # Spec V4.3.3 conformance tests only
composer infection         # mutation testing, MSI >= 80
composer sbom              # CycloneDX 1.5 SBOM from composer.lock
```

`composer.lock` is committed and CI pins every action by commit SHA and kcode
by version and SHA-256 (§32). kcode's binary is `kcode`, this package's is
`kcli` (ADR-006). CONTRIBUTING.md lists the code rules and
docs/quality/QD-V433-COMPLIANCE.md the current gate status.

## License

MIT — © 2026 KaririCode Framework / Walmir Silva
