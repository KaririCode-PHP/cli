<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Inspector;

use KaririCode\Cli\Inspector\RegexComplianceEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A whole scan, row by row: files in path order with paths relative to the
 * scanned directory (subdirectories included, non-PHP files skipped), the
 * line of each finding, its severity and message, and the summary counts.
 */
#[CoversClass(RegexComplianceEngine::class)]
final class RegexComplianceEngineGoldenTest extends TestCase
{
    private const string DOC = "/**\n * Role.\n *\n * Preconditions: none.\n * Thread-safety: n/a.\n * Side effects: none.\n *\n * Example:\n *   x();\n *\n * References: none.\n */\n";

    private const string PARTIAL_DOC = "/**\n * Role.\n *\n * Preconditions: none.\n * Thread-safety: n/a.\n * Side effects: none.\n */\n";

    private const array FILES = ['/Zed.php', '/NoStrict.php', '/sub/Mapper.php', '/README.md', '/Bad.php'];

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/kcli-golden-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/sub', recursive: true);
        // Created out of path order: the scan sorts.
        file_put_contents($this->dir . '/Zed.php', "<?php\n\ndeclare(strict_types=1);\n\n" . self::DOC . "final class Zed {}\n");
        file_put_contents($this->dir . '/NoStrict.php', "<?php\n\n" . self::DOC . "enum Status: string\n{\n    case On = 'on';\n}\n");
        file_put_contents(
            $this->dir . '/sub/Mapper.php',
            "<?php\n\ndeclare(strict_types=1);\n\n" . self::DOC . "final class Mapper\n{\n    public function toArray(): array { return []; }\n\n"
            . "    public static function fromArray(array \$a): self { return new self(); }\n\n    public function jsonSerialize(): mixed { return null; }\n}\n\nfinal class Second {}\n",
        );
        file_put_contents($this->dir . '/README.md', "not php\n");
        // Two structural rows, two short markers, and one 72-column marker with trailing blanks (not reported).
        file_put_contents(
            $this->dir . '/Bad.php',
            "<?php\n\ndeclare(strict_types=1);\n\n" . self::PARTIAL_DOC . "final class Bad\n{\n    // ── Short ──\n\n    // " . str_repeat('─', 69) . "   \n\n    // ── Also short ──\n}\n\nfinal class Other {}\n",
        );
    }

    protected function tearDown(): void
    {
        foreach (self::FILES as $file) {
            if (is_file($this->dir . $file)) {
                unlink($this->dir . $file);
            }
        }
        rmdir($this->dir . '/sub');
        rmdir($this->dir);
    }

    public function testEveryRowAndTheSummary(): void
    {
        $result = (new RegexComplianceEngine())->scan($this->dir . '/');

        self::assertSame([
            ['code' => 'V09', 'severity' => 'P1', 'file' => 'Bad.php', 'line' => '12', 'message' => 'More than one class in one file'],
            ['code' => 'V15', 'severity' => 'P1', 'file' => 'Bad.php', 'line' => '12', 'message' => 'Type docblock lacks Example:, References: (Spec V4.3.3 §17)'],
            ['code' => 'V17', 'severity' => 'P1', 'file' => 'Bad.php', 'line' => '14', 'message' => 'Section marker is not 72 columns'],
            ['code' => 'V17', 'severity' => 'P1', 'file' => 'Bad.php', 'line' => '18', 'message' => 'Section marker is not 72 columns'],
            ['code' => 'V01', 'severity' => 'P0', 'file' => 'NoStrict.php', 'line' => '1', 'message' => 'Missing declare(strict_types=1) as the first statement'],
            ['code' => 'V03', 'severity' => 'P1', 'file' => 'NoStrict.php', 'line' => '15', 'message' => 'Backed enum: document the external contract that needs the backing (E-003)'],
            ['code' => 'V04a', 'severity' => 'P0', 'file' => 'sub/Mapper.php', 'line' => '19', 'message' => 'toArray() on a domain type (E-001)'],
            ['code' => 'V04b', 'severity' => 'P0', 'file' => 'sub/Mapper.php', 'line' => '21', 'message' => 'fromArray() on a domain type (E-001)'],
            ['code' => 'V07', 'severity' => 'P1', 'file' => 'sub/Mapper.php', 'line' => '23', 'message' => 'jsonSerialize() on a domain type; implement SerializableData instead (E-001)'],
            ['code' => 'V09', 'severity' => 'P1', 'file' => 'sub/Mapper.php', 'line' => '17', 'message' => 'More than one class in one file'],
        ], $result->rows);
        self::assertSame(
            ['files_scanned' => 4, 'p0_violations' => 3, 'p1_violations' => 7, 'total' => 10, 'status' => 'VIOLATIONS FOUND', 'engine' => 'regex'],
            $result->summary,
        );
        self::assertSame('ARFA 1.3 / Spec V4.3.3 compliance', $result->componentName);
    }

    public function testACleanTreeIsCompliant(): void
    {
        foreach (['/NoStrict.php', '/sub/Mapper.php', '/Bad.php'] as $file) {
            file_put_contents($this->dir . $file, "<?php\n\ndeclare(strict_types=1);\n\n" . self::DOC . 'final class ' . basename($file, '.php') . " {}\n");
        }

        $result = (new RegexComplianceEngine())->scan($this->dir);

        self::assertSame([], $result->rows);
        self::assertSame('COMPLIANT', $result->summary['status']);
        self::assertSame(0, $result->summary['total']);
    }

    /** A file without a type declaration has no docblock to check; its findings sit on line 1. */
    public function testAFileWithoutATypeDeclaration(): void
    {
        file_put_contents($this->dir . '/Zed.php', "<?php\n\ndeclare(strict_types=1);\n\nfunction helper(object \$o): void {}\n");

        $rows = array_values(array_filter((new RegexComplianceEngine())->scan($this->dir)->rows, static fn(array $row): bool => $row['file'] === 'Zed.php'));

        self::assertSame([['code' => 'V18', 'severity' => 'P1', 'file' => 'Zed.php', 'line' => '5', 'message' => 'object-typed parameter; couple through kariricode/contract or a PSR (Spec V4.3.3 §31)']], $rows);
    }

    public function testAMissingDirectoryIsReportedInTheSummary(): void
    {
        $result = (new RegexComplianceEngine())->scan($this->dir . '/missing');

        self::assertSame([], $result->rows);
        self::assertSame(['error' => 'Directory not found', 'engine' => 'regex'], $result->summary);
    }
}
