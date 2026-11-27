# ADR-005 — Generators: Filesystem Isolation via FileWriter

**Status:** Accepted
**Date:** 2026-03-08
**Component:** KaririCode\Cli V4.1

---

## Context

Code generators that directly call `file_put_contents()` are impossible to test without temporary directories, cleanup hooks, and platform-dependent path handling. Generator tests become integration tests by default, making them slow and fragile.

Laravel's `GeneratorCommand` writes from inside the command through its `Filesystem` (`$this->files->put()`), so testing the generated content means touching a disk or a virtual filesystem such as `vfsStream`.

## Decision

Generators are **pure functions**: they accept a `GeneratorContext` and return `list<GeneratedFile>`. They have zero filesystem interaction. All I/O is delegated to `FileWriter`, which is injected at the command level:

```
Command → Generator::generate(context) → [GeneratedFile] → FileWriter::writeAll() → [WriteOutcome]
```

`FileWriter` supports two modes:
- `dryRun: true` — returns outcomes with full content for preview, writes nothing
- `dryRun: false` — writes to disk, detects existing files, reports CREATED/UPDATED

### Amendment (4.1.0)

`writeAll()` checks every target for collisions before the first write, so multi-file generators (`make:repository`) either write all files or none. Statuses are the pure enum `WriteStatus`.

### Alignment

ARFA 1.3 P4: Generators are protocol-agnostic — they produce content without knowing whether it will be written to disk, displayed in a terminal, or asserted in a test.

## Consequences

**Positive:**
- Generator tests assert on string content — no filesystem, no cleanup
- Dry-run is a first-class feature, not an afterthought
- FileWriter can be tested independently with actual filesystem
- Commands compose Generator + FileWriter, following SRP

**Negative:**
- Two objects per generation (Generator + FileWriter) vs. one monolithic generator
- FileWriter must be passed or created in every command

## Alternatives Considered

- **Generators writing directly** (Laravel's `GeneratorCommand` calls `$this->files->put()` from the command) — rejected: generator tests would need a filesystem.
- **vfsStream in tests** — rejected: an extra dev dependency to emulate something the design can avoid.
- **Temp file + `rename()` for atomic writes** — deferred; 4.1.0 uses `LOCK_EX` plus the batch pre-flight below.

## References

- Laravel Framework — `Illuminate\Console\GeneratorCommand`.
- PHP Manual — `file_put_contents()` with `LOCK_EX`.
