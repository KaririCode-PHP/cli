<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Application;

use KaririCode\Cli\Application\FileWriter;
use KaririCode\Cli\Enum\WriteStatus;
use KaririCode\Cli\ValueObject\GeneratedFile;
use KaririCode\Contract\Exception\ExceptionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileWriter::class)]
final class FileWriterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/kcli-writer-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testDryRunReturnsContentAndWritesNothing(): void
    {
        $outcome = (new FileWriter(dryRun: true))->write($this->file('A.php'));

        self::assertSame(WriteStatus::DryRun, $outcome->status);
        self::assertTrue($outcome->isDryRun());
        self::assertSame("<?php\n", $outcome->content);
        self::assertDirectoryDoesNotExist($this->dir);
    }

    public function testCreatesThenUpdatesWithForce(): void
    {
        $created = (new FileWriter())->write($this->file('A.php'));
        $updated = (new FileWriter(force: true))->write($this->file('A.php'));

        self::assertSame(WriteStatus::Created, $created->status);
        self::assertSame(WriteStatus::Updated, $updated->status);
        self::assertFileExists($this->dir . '/A.php');
    }

    public function testExistingFileWithoutForceThrows(): void
    {
        (new FileWriter())->write($this->file('A.php'));

        $this->expectException(ExceptionInterface::class);
        $this->expectExceptionCode(8940);

        (new FileWriter())->write($this->file('A.php'));
    }

    public function testBatchPreflightWritesNothingWhenAnyTargetExists(): void
    {
        // Regression: make:repository wrote the interface, then failed on the
        // implementation and left a half-generated pair behind.
        (new FileWriter())->write($this->file('B.php'));

        try {
            (new FileWriter())->writeAll([$this->file('A.php'), $this->file('B.php')]);
            self::fail('Expected ScaffoldFailureException 8940');
        } catch (ExceptionInterface $e) {
            self::assertSame(8940, $e->getCode());
        }

        self::assertFileDoesNotExist($this->dir . '/A.php');
    }

    private function file(string $name): GeneratedFile
    {
        return new GeneratedFile(path: "{$this->dir}/{$name}", content: "<?php\n", label: $name);
    }
}
