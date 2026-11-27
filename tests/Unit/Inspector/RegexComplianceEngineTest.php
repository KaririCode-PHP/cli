<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Inspector;

use KaririCode\Cli\Inspector\RegexComplianceEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegexComplianceEngine::class)]
final class RegexComplianceEngineTest extends TestCase
{
    private const string DOC = "/**\n * Role.\n *\n * Preconditions: none.\n * Thread-safety: n/a.\n * Side effects: none.\n *\n * Example:\n *   x();\n *\n * References: none.\n */\n";

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/kcli-engine-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    /** @return iterable<string, array{string, string}> */
    public static function violations(): iterable
    {
        yield 'V10 boilerplate tag'       => ['V10', "/**\n * @author Someone\n */\nfinal class A {}"];
        yield 'V11 untyped constant'      => ['V11', "final class A\n{\n    private const LIMIT = 3;\n}"];
        yield 'V12 dollar end anchor'     => ['V12', "final class A\n{\n    public function f(): bool { return preg_match('/^a\$/', 'a') === 1; }\n}"];
        yield 'V13 getter'                => ['V13', "final class A\n{\n    public function getName(): string { return ''; }\n}"];
        yield 'V14 hook in readonly'      => ['V14', "final readonly class A\n{\n    public string \$x { get => 'x'; }\n}"];
        yield 'V16 generic exception'     => ['V16', "final class A\n{\n    public function f(): never { throw new \\RuntimeException('x'); }\n}"];
        yield 'V17 short marker'          => ['V17', "final class A\n{\n    // ── Short ──\n}"];
        yield 'V18 object parameter'      => ['V18', "final class A\n{\n    public function f(object \$o): void {}\n}"];
    }

    #[DataProvider('violations')]
    public function testDetectsSpecV433Violation(string $code, string $body): void
    {
        file_put_contents($this->dir . '/A.php', "<?php\n\ndeclare(strict_types=1);\n\n" . self::DOC . $body . "\n");

        $codes = array_column((new RegexComplianceEngine())->scan($this->dir)->rows, 'code');

        self::assertContains($code, $codes);
    }

    public function testMissingDocumentationElementsAreReported(): void
    {
        file_put_contents($this->dir . '/A.php', "<?php\n\ndeclare(strict_types=1);\n\n/** Role only. */\nfinal class A {}\n");

        $rows = (new RegexComplianceEngine())->scan($this->dir)->rows;

        self::assertSame(['V15'], array_column($rows, 'code'));
        self::assertStringContainsString('Preconditions:', $rows[0]['message']);
    }

    public function testCompliantFileProducesNoRows(): void
    {
        file_put_contents(
            $this->dir . '/A.php',
            "<?php\n\ndeclare(strict_types=1);\n\n" . self::DOC . "final class A\n{\n    private const int LIMIT = 3;\n\n    public string \$name {\n        get => 'a';\n    }\n}\n",
        );

        $result = (new RegexComplianceEngine())->scan($this->dir);

        self::assertSame([], $result->rows);
        self::assertSame('COMPLIANT', $result->summary['status']);
        self::assertSame('regex', $result->summary['engine']);
    }
}
