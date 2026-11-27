# ADR-002 — FieldInferenceEngine: Name-Driven Processor Injection

**Status:** Accepted  
**Date:** 2026-03-06  
**Component:** KaririCode\Cli V4.0

---

## Context

Laravel's `make:model` and `make:request` generate bare class skeletons. Developers must manually add validation rules for every field. For common patterns (email, CPF, CNPJ, password, slug, phone) this is repetitive and error-prone — developers often forget sanitizers, generating security issues (untrimmed emails, un-sanitized HTML).

## Decision

`FieldInferenceEngine` maps field names and declared types to a curated set of `KaririCode\Validator` and `KaririCode\Sanitizer` class names using two-pass rule lookup:

1. **Name pass** — exact match on `strtolower($fieldName)` against a 30-entry name table
2. **Type pass** — match on `FieldType` enum for domain types (Email, Cpf, Cnpj, Url, Phone, Money)

Name rules take precedence. Explicit validators/sanitizers provided by the caller are merged first (caller always wins).

```
email:string → NotBlank, Email / Trim, Lowercase
password:string → NotBlank, Length(min:8) / Trim
cpf:cpf → NotBlank, Cpf / Trim, Digits
price:money → NotBlank, Positive / (none)
```

### Alignment

ARFA 1.3 P3 (Adaptive Context Awareness): The engine adapts to the semantic meaning of field names, producing a richer output than the raw input alone could justify.

## Consequences

**Positive:**
- Generated DPOs are production-safe immediately
- Eliminates class of "missing validation" bugs
- Brazilian domain types (CPF, CNPJ, CEP) handled natively
- Fully extensible — caller can override any inferred rule

**Negative:**
- Opinionated defaults may not match every project's validation library
- Name table must be maintained as KaririCode\Validator grows

## Alternatives Considered

- **No inference** (bare typed fields, as `make:*` generators commonly emit) — rejected: every DPO would need its attributes written by hand.
- **Type-only inference** — insufficient: `name` and `email` are both `string` but need different processors.
- **External mapping file** — rejected: indirection with no benefit over the in-code rule tables.

## References

- Ruby on Rails Doctrine — "Convention over Configuration" (rubyonrails.org/doctrine).
- KaririCode\Validator / KaririCode\Sanitizer V4 — attribute-driven processors consumed by the generated DPO.
