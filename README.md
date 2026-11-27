# KaririCode\Cli

[![PHP](https://img.shields.io/badge/PHP-8.4%2B-blue)](https://www.php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![ARFA](https://img.shields.io/badge/ARFA-1.3-orange)](https://kariricode.org/arfa)
[![Spec](https://img.shields.io/badge/Spec-V4.0-purple)](https://kariricode.org/spec)

**The most professional PHP developer CLI on the market.** Code generation, ecosystem inspection, and ARFA 1.3 compliance checking — zero framework coupling, zero inheritance required.

---

## Why KaririCode CLI beats Laravel Artisan

| Feature | Laravel Artisan | KaririCode CLI V4.0 |
|---|---|---|
| Command design | Must extend `Illuminate\Console\Command` | Pure interface — zero inheritance |
| Code generation | Bare skeleton, no validation hints | DPO with auto-inferred validators + sanitizers |
| Input typing | `$this->argument()` returns `mixed` | Readonly DTO via Console V4.1 `InputMapper` |
| Brazilian types | None | CPF, CNPJ, CEP, Phone validators built-in |
| Compliance check | None | `check:arfa` — Spec V4.3.3 static analysis |
| Ecosystem health | None | `check:ecosystem` — V4.0 baseline verification |
| Dry-run | None | `--dry-run` on every `make:*` command |
| Protocol agnostic | Requires CLI process | `ArrayInput` runs commands in tests / HTTP |
| PHP version | PHP 7.x compatible | PHP 8.4 required — full readonly/enum/hooks |
| Dependencies | Laravel full stack | `kariricode/console` only |

---

## Installation

```bash
composer require kariricode/cli
```

Make executable:

```bash
chmod +x vendor/bin/kcli
```

Or install globally:

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

The DPO is KaririCode's production-ready alternative to DTOs and Laravel FormRequests. Fields are inferred from their names: `email` automatically gets `NotBlank` + `Email` validators and `Trim` + `Lowercase` sanitizers. Zero manual setup.

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
| `url` / `website` | Url | Trim |
| `zip_code` | NotBlank, ZipCode | Trim, Digits |
| `price` / `amount` | NotBlank, Positive | (none) |
| `uuid` | NotBlank, Uuid | Trim, Lowercase |

### `make:command` — Console V4.1 Command

```bash
kcli make:command SendWelcomeEmail --namespace=App\\Command
```

Generates a command that implements `KaririCode\Console\Contract\Command` directly. No `extends`. Fully injectable and testable.

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

Verifies that all installed KaririCode packages meet the minimum versions this CLI expects.

```bash
kcli check:ecosystem
kcli check:ecosystem --json
```

---

## Error Codes

Block 8600–8699; each exception implements one `kariricode/contract` family
and its message starts with `[CLI_86xx]` (ADR-009).

| Code | Family | Factory |
|---|---|---|
| 8600 | Validation | `InvalidScaffoldRequestException::invalidClassName()` |
| 8601 | Validation | `InvalidScaffoldRequestException::invalidNamespace()` |
| 8602 | Validation | `InvalidScaffoldRequestException::invalidFieldDefinition()` |
| 8620 | NotFound | `GeneratorNotFoundException::forTarget(string $target)` |
| 8640 | Runtime | `ScaffoldFailureException::fileExists(string $path)` |
| 8641 | Runtime | `ScaffoldFailureException::writeFailed(string $path, string $reason)` |
| 8642 | Runtime | `ScaffoldFailureException::registrySealed(string $target)` |


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
│   ├── Conformance/
│   │   ├── SelfComplianceTest.php
│   │   └── SpecConformanceTest.php
│   └── Unit/
│       ├── Application/
│       │   ├── FileWriterTest.php
│       │   └── ScaffoldRunnerTest.php
│       ├── Command/
│       ├── Enum/
│       │   └── ViolationSeverityTest.php
│       ├── Generator/
│       │   ├── DpoGeneratorTest.php
│       │   ├── EntityGeneratorTest.php
│       │   ├── GeneratorRegistryTest.php
│       │   ├── ParserGeneratorsTest.php
│       │   └── ValueObjectGeneratorTest.php
│       ├── Inference/
│       │   └── FieldInferenceEngineTest.php
│       ├── Inspector/
│       │   ├── ArfaComplianceInspectorTest.php
│       │   └── RegexComplianceEngineTest.php
│       ├── Template/
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
│   │   └── ADR-012-documentation-and-generated-code.md
│   ├── quality/
│   ├── security/
│   │   └── SECURITY.md
│   ├── spec/
│   │   └── SPEC-001-cli.md
│   └── README.md
├── .github/
│   └── workflows/
│       └── ci.yml
├── composer.json
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

Quality gates live in this repository (ADR-010) and run through Composer:

```bash
composer check             # cs-check, phpstan (max), psalm (level 1), complexity, test, security
composer test-conformance  # Spec V4.3.3 conformance tests only
composer infection         # mutation testing, MSI >= 80
composer sbom              # CycloneDX 1.5 SBOM from composer.lock
```

`composer.lock` is committed and CI pins every action by commit SHA (§32).
KaririCode\Devkit can still be used locally; its binary is `vendor/bin/kcode`,
this package's is `vendor/bin/kcli` (ADR-006). CONTRIBUTING.md lists the code
rules and docs/quality/QD-V433-COMPLIANCE.md the current gate status.

## License

MIT — © 2026 KaririCode Framework / Walmir Silva
