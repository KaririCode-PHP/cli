# KaririCode\Cli — Documentation Index

## Specification

- [SPEC-001 — KaririCode\Cli](spec/SPEC-001-cli.md) (Spec V4.3.3)

## Architecture Decision Records

| ADR | Title | Status |
|---|---|---|
| [ADR-001](adr/ADR-001-interface-based-commands.md) | Interface-Based Commands: Zero Inheritance | Accepted; amended 2026-11-27 (console 4.6) |
| [ADR-002](adr/ADR-002-field-inference-engine.md) | FieldInferenceEngine: Name-Driven Processor Injection | Accepted |
| [ADR-003](adr/ADR-003-generator-registry-auto-seal.md) | GeneratorRegistry: Two-Phase Lifecycle with Auto-Seal | Accepted |
| [ADR-004](adr/ADR-004-compliance-engines.md) | ArfaComplianceInspector: Dual-Engine Detection | Accepted |
| [ADR-005](adr/ADR-005-filesystem-isolation.md) | Generators: Filesystem Isolation via FileWriter | Accepted |
| [ADR-006](adr/ADR-006-binary-name-kcli.md) | Binary Name `kcli`: Coexistence with KaririCode\Devkit | Accepted |
| [ADR-007](adr/ADR-007-devkit-tooling.md) | Quality Tooling Delegated to KaririCode\Devkit | Superseded by ADR-010 (4.1.0) |
| [ADR-008](adr/ADR-008-scaffold-pipeline.md) | Shared Scaffold Pipeline and Self-Validating Context | Accepted |
| [ADR-009](adr/ADR-009-error-model.md) | Error Model: Contract Exception Families in Block 8900–8999 | Accepted |
| [ADR-010](adr/ADR-010-spec-tooling-and-supply-chain.md) | Spec Quality Gates and Supply Chain in the Repository | Accepted; amended by ADR-013 |
| [ADR-011](adr/ADR-011-component-boundaries.md) | Component Boundaries: Contract, Console Host and CLI-Owned Ports | Accepted |
| [ADR-012](adr/ADR-012-documentation-and-generated-code.md) | Documentation Elements, Generated Code and No Ambient Time | Accepted |
| [ADR-013](adr/ADR-013-toolchain-through-kcode.md) | Quality Gate through KaririCode Devkit, with the Root Configs | Accepted |

## Quality and security

- [QD-V433-COMPLIANCE](quality/QD-V433-COMPLIANCE.md): gate status and conformance evidence
- [SECURITY](security/SECURITY.md): reporting, threat model, ASVS 5.0 level 1 scope
- [CONTRIBUTING](../CONTRIBUTING.md): workflow and code rules
