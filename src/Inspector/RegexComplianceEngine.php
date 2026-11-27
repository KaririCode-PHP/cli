<?php

declare(strict_types=1);

namespace KaririCode\Cli\Inspector;

use KaririCode\Cli\Contract\ComplianceEngine;
use KaririCode\Cli\ValueObject\InspectionResult;

/**
 * Dependency-free ComplianceEngine for the statically checkable rules of
 * ARFA 1.3 / Spec V4.3.3.
 *
 * ARFA 1.3: P4. Spec V4.3.3: stateless service. Rules V01–V07 come from the
 * V4.0 errata; V09–V18 cover §17 (documentation), §31 (coupling), E-040
 * (hooks in readonly classes) and the conformance checks the V4.3.3
 * components run as tests.
 *
 * Preconditions: files are UTF-8 PHP sources.
 * Thread-safety: no state; safe to share.
 * Side effects: reads every *.php file under the scanned directory.
 *
 * Example:
 *   $rows = (new RegexComplianceEngine())->scan('src')->rows;
 *
 * References: ADR-004; SPEC-001 §9 lists every rule.
 * @see https://www.pcre.org/current/doc/html/pcre2pattern.html
 */
final class RegexComplianceEngine implements ComplianceEngine
{
    private const string COMPONENT = 'ARFA 1.3 / Spec V4.3.3 compliance';

    /** @var array<string, array{pattern: string, severity: string, message: string}> */
    private const array PATTERN_RULES = [
        'V01' => [
            'pattern'  => '/\A(?:#![^\n]*\n)?<\?php(?:\s++|\/\*.*?\*\/|\/\/[^\n]*+|#(?!\[)[^\n]*+)*+(?!declare\s*\(\s*strict_types\s*=\s*1\s*\))/si',
            'severity' => 'P0',
            'message'  => 'Missing declare(strict_types=1) as the first statement',
        ],
        'V03' => [
            'pattern'  => '/^\s*enum\s+\w+\s*:\s*(?:string|int)\b/m',
            'severity' => 'P1',
            'message'  => 'Backed enum: document the external contract that needs the backing (E-003)',
        ],
        'V04a' => [
            'pattern'  => '/public\s+function\s+toArray\s*\(/',
            'severity' => 'P0',
            'message'  => 'toArray() on a domain type (E-001)',
        ],
        'V04b' => [
            'pattern'  => '/public\s+(?:static\s+)?function\s+fromArray\s*\(/',
            'severity' => 'P0',
            'message'  => 'fromArray() on a domain type (E-001)',
        ],
        'V07' => [
            'pattern'  => '/public\s+function\s+jsonSerialize\s*\(/',
            'severity' => 'P1',
            'message'  => 'jsonSerialize() on a domain type; implement SerializableData instead (E-001)',
        ],
        'V10' => [
            'pattern'  => '/^\s*\*\s*@(?:package|author|license|since|copyright|category|version)\b/m',
            'severity' => 'P1',
            'message'  => 'Boilerplate docblock tag (Spec V4.3.3 §17)',
        ],
        'V11' => [
            'pattern'  => '/^\s*(?:(?:final|public|protected|private)\s+)*const\s+[A-Z][A-Z0-9_]*\s*=/m',
            'severity' => 'P1',
            'message'  => 'Untyped class constant',
        ],
        'V12' => [
            'pattern'  => '/preg_[a-z_]+\(\s*\x27[^\x27]*\$\/(?![a-zA-Z]*m)[a-zA-Z]*\x27/',
            'severity' => 'P1',
            'message'  => 'Regex anchored with $ instead of \z',
        ],
        'V13' => [
            'pattern'  => '/public\s+function\s+get[A-Z]\w*\s*\(\s*\)/',
            'severity' => 'P1',
            'message'  => 'Getter method; expose a property instead',
        ],
        'V16' => [
            'pattern'  => '/throw\s+new\s+\\\\?(?:Exception|ErrorException|RuntimeException|LogicException|InvalidArgumentException|DomainException|UnexpectedValueException)\s*\(/',
            'severity' => 'P1',
            'message'  => 'Generic exception; throw one that implements a kariricode/contract family',
        ],
        'V18' => [
            'pattern'  => '/function\s+\w+\s*\([^)]*\??\bobject\s+\$/',
            'severity' => 'P1',
            'message'  => 'object-typed parameter; couple through kariricode/contract or a PSR (Spec V4.3.3 §31)',
        ],
    ];

    private const string CLASS_DECLARATION = '/\n\s*(?:final\s+|abstract\s+|readonly\s+)*class\s+\w+/';

    private const string TYPE_DECLARATION = '/^(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+\w+/m';

    private const string READONLY_CLASS = '/^(?:final\s+)?readonly\s+(?:final\s+)?class\b/m';

    private const string PROPERTY_HOOK = '/\$\w+\s*\{\s*(?:get|set)\b/';

    private const string SECTION_MARKER = '/^\s*(\/\/ ──.*)$/mu';

    /** @var list<string> */
    private const array DOC_LABELS = ['Preconditions:', 'Thread-safety:', 'Side effects:', 'Example:', 'References:'];

    public string $name {
        get => 'regex';
    }

    #[\Override]
    public function scan(string $directory): InspectionResult
    {
        if (!is_dir($directory)) {
            return new InspectionResult(
                componentName: self::COMPONENT,
                rows: [],
                summary: ['error' => 'Directory not found', 'engine' => $this->name],
            );
        }

        $files = $this->phpFiles($directory);
        $rows  = [];

        foreach ($files as $file) {
            // Forward slashes on every platform: on Windows the iterator joins
            // with a backslash, and reports would differ between machines.
            $relative = str_replace('\\', '/', substr($file, strlen(rtrim($directory, '/\\')) + 1));
            array_push($rows, ...$this->scanFile($relative, (string) file_get_contents($file)));
        }

        $p0 = count(array_filter($rows, static fn(array $row): bool => $row['severity'] === 'P0'));

        return new InspectionResult(
            componentName: self::COMPONENT,
            rows: $rows,
            summary: [
                'files_scanned' => count($files),
                'p0_violations' => $p0,
                'p1_violations' => count($rows) - $p0,
                'total'         => count($rows),
                'status'        => $rows === [] ? 'COMPLIANT' : 'VIOLATIONS FOUND',
                'engine'        => $this->name,
            ],
        );
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $files    = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $entry RecursiveDirectoryIterator yields SplFileInfo by default */
        foreach ($iterator as $entry) {
            if ($entry->isFile() && $entry->getExtension() === 'php') {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /** @return list<array{code: string, severity: string, file: string, line: string, message: string}> */
    private function scanFile(string $file, string $source): array
    {
        $rows = [];

        foreach (self::PATTERN_RULES as $code => $rule) {
            foreach ($this->matchLines($rule['pattern'], $source) as $line) {
                $rows[] = $this->row($code, $rule, $file, $line);
            }
        }

        return [...$rows, ...$this->structuralRows($file, $source), ...$this->markerRows($file, $source)];
    }

    /** @return list<array{code: string, severity: string, file: string, line: string, message: string}> */
    private function structuralRows(string $file, string $source): array
    {
        $rows        = [];
        $declaration = $this->matchLines(self::TYPE_DECLARATION, $source)[0] ?? 1;
        $missing     = array_values(array_filter(
            self::DOC_LABELS,
            static fn(string $label): bool => !str_contains($source, $label),
        ));

        if ((int) preg_match_all(self::CLASS_DECLARATION, $source) > 1) {
            $rows[] = $this->row('V09', ['severity' => 'P1', 'message' => 'More than one class in one file'], $file, $declaration);
        }

        if (preg_match(self::READONLY_CLASS, $source) === 1 && preg_match(self::PROPERTY_HOOK, $source) === 1) {
            $rows[] = $this->row('V14', ['severity' => 'P0', 'message' => 'Property hook in a readonly class (E-040)'], $file, $declaration);
        }

        if ($missing !== [] && preg_match(self::TYPE_DECLARATION, $source) === 1) {
            $message = 'Type docblock lacks ' . implode(', ', $missing) . ' (Spec V4.3.3 §17)';
            $rows[]  = $this->row('V15', ['severity' => 'P1', 'message' => $message], $file, $declaration);
        }

        return $rows;
    }

    /** @return list<array{code: string, severity: string, file: string, line: string, message: string}> */
    private function markerRows(string $file, string $source): array
    {
        $rows = [];

        if ((int) preg_match_all(self::SECTION_MARKER, $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        foreach ($matches[1] as [$marker, $offset]) {
            if (mb_strlen(rtrim($marker)) !== 72) {
                $line   = substr_count($source, "\n", 0, $offset) + 1;
                $rows[] = $this->row('V17', ['severity' => 'P1', 'message' => 'Section marker is not 72 columns'], $file, $line);
            }
        }

        return $rows;
    }

    /**
     * @param  non-empty-string $pattern
     * @return list<int>
     */
    private function matchLines(string $pattern, string $source): array
    {
        if ((int) preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        return array_map(
            static fn(array $match): int => substr_count($source, "\n", 0, $match[1]) + 1,
            $matches[0],
        );
    }

    /**
     * @param  array{pattern?: string, severity: string, message: string} $rule
     * @return array{code: string, severity: string, file: string, line: string, message: string}
     */
    private function row(string $code, array $rule, string $file, int $line): array
    {
        return [
            'code'     => $code,
            'severity' => $rule['severity'],
            'file'     => $file,
            'line'     => (string) $line,
            'message'  => $rule['message'],
        ];
    }
}
