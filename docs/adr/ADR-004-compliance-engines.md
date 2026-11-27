# ADR-004 — ArfaComplianceInspector: Dual-Engine Detection

**Status:** Accepted
**Date:** 2026-03-08
**Component:** KaririCode\Cli V4.1

---

## Context

ARFA 1.3 compliance checking requires detection of antipatterns such as `toArray()`, `fromArray()`, backed enums, missing `strict_types`, and multi-class files. Two fundamentally different detection strategies exist:

1. **AST-based** — Zero false positives, span-precise, but requires `kariricode/parser` as a dependency
2. **Regex-based** — Fast, zero-dependency, but produces false positives on strings/comments and misses multi-line patterns

Not all users install the Parser. A compliance tool that silently fails or requires an optional dependency to function at all would discourage adoption.

## Decision

ArfaComplianceInspector implements a dual-engine strategy with automatic fallback:

```
if class_exists(KaririCode\Parser\Inspector\ArfaComplianceInspector)
    → delegate to AST engine (span-precise, zero false positives)
else
    → use built-in regex engine (good-enough, zero dependencies)
```

The active engine is reported in the inspection summary under `engine: 'ast'` or `engine: 'regex (...)'` so the user always knows which mode produced the results.

### Alignment

ARFA 1.3 P4 (Protocol Agnosticism): The inspector's output is the same `InspectionResult` regardless of engine, enabling format-agnostic rendering (table, JSON, CI annotations).

### Amendment (4.1.0, Spec V4.3.3)

The engines now sit behind the `ComplianceEngine` port (ADR-011). The
`class_exists()` probe of `kariricode/parser` and every Parser class name are
gone: `RegexComplianceEngine` is the default, with rules V01–V18 (SPEC-001
§9), and an AST engine plugs in by implementing the port and binding it in
the container. `check:arfa` reports the engine in `summary.engine`.

## Consequences

**Positive:**
- `check:arfa` works out of the box with zero optional dependencies
- Users who install the Parser get automatic upgrade to AST precision
- CI pipelines can enforce AST-only mode by checking the `engine` summary field
- Regex rules are simple, auditable, and serve as a specification of what the AST engine must also detect

**Negative:**
- Two code paths to maintain (mitigated: regex rules are static constants)
- Regex engine may produce false positives in edge cases (documented in output)

## Alternatives Considered

- **AST only** — rejected: would make `kariricode/parser` a hard dependency.
- **Regex only** — rejected: false positives inside strings and comments are unavoidable.
- **`token_get_all()` tokenizer as fallback** — not adopted in 4.1; the best candidate to replace the regex engine without adding a dependency.

## References

- PCRE2 pattern documentation — "Atomic grouping and possessive quantifiers". The V01 rule uses possessive quantifiers since 4.1.0, because `\s*(?!declare…)` backtracked and flagged every compliant file.
- PHP Manual — `token_get_all()`.
