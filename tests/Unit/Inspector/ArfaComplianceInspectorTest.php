<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Inspector;

use KaririCode\Cli\Inspector\ArfaComplianceInspector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArfaComplianceInspector::class)]
final class ArfaComplianceInspectorTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/arfa-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tmpDir);
    }

    public function testGetComponentName(): void
    {
        $inspector = new ArfaComplianceInspector($this->tmpDir);
        self::assertSame('ARFA 1.3 / Spec V4.3.3 compliance', $inspector->componentName);
    }

    public function testCleanFileProducesNoViolations(): void
    {
        $this->writeFile('Clean.php', <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace App;

            /**
             * A compliant type carries the five documentation elements (Spec V4.3.3 §17).
             *
             * Preconditions: none.
             * Thread-safety: stateless.
             * Side effects: none.
             *
             * Example:
             *   new Clean();
             *
             * References: SPEC-001.
             * @see https://www.php.net/manual/en/language.oop5.php
             */
            final class Clean { }
            PHP);

        $result = (new ArfaComplianceInspector($this->tmpDir))->inspect();

        self::assertSame(0, $result->summary['total']);
        self::assertSame('COMPLIANT', $result->summary['status']);
    }

    public function testMissingStrictTypesIsP0Violation(): void
    {
        $this->writeFile('NoStrict.php', <<<'PHP'
            <?php
            namespace App;
            final class NoStrict { }
            PHP);

        $result = (new ArfaComplianceInspector($this->tmpDir))->inspect();

        $codes = array_column($result->rows, 'code');
        self::assertContains('V01', $codes);
    }

    public function testBackedEnumIsP1Violation(): void
    {
        // Per E-003, backed enums are permitted for external
        // contracts but require justification — flagged as P1 (review), not P0.
        $this->writeFile('BackedEnum.php', <<<'PHP'
            <?php
            declare(strict_types=1);
            enum Status: string { case Active = 'active'; }
            PHP);

        $result = (new ArfaComplianceInspector($this->tmpDir))->inspect();

        $codes = array_column($result->rows, 'code');
        self::assertContains('V03', $codes);

        $row = array_values(array_filter($result->rows, fn($r) => $r['code'] === 'V03'))[0];
        self::assertSame('P1', $row['severity']);
    }

    public function testToArrayAntipatternIsP0Violation(): void
    {
        $this->writeFile('HasToArray.php', <<<'PHP'
            <?php
            declare(strict_types=1);
            final class HasToArray {
                public function toArray(): array { return []; }
            }
            PHP);

        $result = (new ArfaComplianceInspector($this->tmpDir))->inspect();

        $codes = array_column($result->rows, 'code');
        self::assertContains('V04a', $codes);
    }

    public function testJsonSerializeIsP1Violation(): void
    {
        $this->writeFile('HasJsonSerialize.php', <<<'PHP'
            <?php
            declare(strict_types=1);
            final class HasJsonSerialize {
                public function jsonSerialize(): mixed { return []; }
            }
            PHP);

        $result = (new ArfaComplianceInspector($this->tmpDir))->inspect();

        $codes = array_column($result->rows, 'code');
        self::assertContains('V07', $codes);
        $row = array_values(array_filter($result->rows, fn($r) => $r['code'] === 'V07'))[0];
        self::assertSame('P1', $row['severity']);
    }

    public function testNonExistentDirectoryReturnsErrorSummary(): void
    {
        $result = (new ArfaComplianceInspector('/non/existent/path'))->inspect();

        self::assertArrayHasKey('error', $result->summary);
    }

    public function testFilesScannedCountIsAccurate(): void
    {
        $this->writeFile('File1.php', "<?php\ndeclare(strict_types=1);\nfinal class File1 {}");
        $this->writeFile('File2.php', "<?php\ndeclare(strict_types=1);\nfinal class File2 {}");

        $result = (new ArfaComplianceInspector($this->tmpDir))->inspect();

        self::assertSame(2, $result->summary['files_scanned']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function writeFile(string $name, string $content): void
    {
        file_put_contents($this->tmpDir . '/' . $name, $content);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    public function testDeclareAfterBlankLinesOrHeaderCommentIsNotFlagged(): void
    {
        // Regression: '\s*(?!declare…)' backtracked one whitespace character
        // and reported V01 on every compliant file.
        $this->writeFile('Blank.php', "<?php\n\ndeclare(strict_types=1);\n\nfinal class Blank {}\n");
        $this->writeFile('Header.php', "<?php\n\n/**\n * License header.\n */\n\ndeclare(strict_types=1);\n\nfinal class Header {}\n");

        $result = (new ArfaComplianceInspector($this->tmpDir))->inspect();

        self::assertNotContains('V01', array_column($result->rows, 'code'));
    }
}
