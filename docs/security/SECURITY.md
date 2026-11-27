# Security Policy — KaririCode\Cli

## Reporting

Report vulnerabilities privately to walmir.silva@kariricode.org with a
reproduction. Do not open a public issue. Fixes ship in the next patch release
of every supported line, with a CHANGELOG entry once users can update.

| Version | Supported |
|---|---|
| 4.1.x | yes |
| 4.0.x | no (pre-release line) |

## Threat model

`kcli` is a local developer tool. It runs with the permissions of the person
who starts it, opens no sockets and executes none of the code it generates.

| Asset or input | Control |
|---|---|
| Class names and namespaces | validated before any path is built (8600, 8601); only PHP identifiers reach the file system |
| `--fields` entries | `name[:type]` grammar (8602); unknown types fall back to `string` |
| Existing files | never overwritten without `--force` (8640); the batch is checked before the first write; each write takes `LOCK_EX` |
| `--path` and output directories | chosen by the operator, who already owns the working copy |
| `composer.lock` | decoded with depth 512 and `JSON_THROW_ON_ERROR`; failures exit 1 |
| Error messages | validation messages never repeat the rejected value (E-047) |

## ASVS 5.0 — level 1 scope

Applies: input validation (identifiers, field grammar), file handling (no
overwrite, locking, paths derived from validated identifiers), error handling
(contract exception families, no input echo), dependency management (lock,
`composer audit --locked`, SBOM). Not applicable: authentication, sessions,
access control and transport security; the tool has no remote surface.

## Supply chain (§32)

`composer.lock` committed; `composer security` runs the audit, the secret scan
and the `security` test group; `composer sbom` writes a CycloneDX 1.5 SBOM;
CI pins every action by commit SHA with read-only permissions; release tags
are signed (`git tag -s`).
