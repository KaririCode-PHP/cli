<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Template;

use KaririCode\Cli\Enum\OutputFormat;
use KaririCode\Cli\Template\OutputRenderer;
use KaririCode\Cli\ValueObject\InspectionResult;
use KaririCode\Console\Contract\Output;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OutputRenderer::class)]
final class OutputRendererTest extends TestCase
{
    /** @var list<string> */
    private array $lines = [];

    public function testJsonFormatEmitsOnlyValidJson(): void
    {
        // Regression: header, summary and "Completed in" footer were printed
        // around the JSON document, breaking `| jq`.
        (new OutputRenderer())->render($this->inspection(), $this->outputDouble(), OutputFormat::Json);

        $text = implode("\n", $this->lines);

        self::assertTrue(json_validate($text), $text);
        self::assertStringNotContainsString('Completed in', $text);
        self::assertSame('V01', json_decode($text, true)['rows'][0]['code']);
    }

    public function testPlainFormatEmitsOneLinePerRow(): void
    {
        (new OutputRenderer())->render($this->inspection(), $this->outputDouble(), OutputFormat::Plain);

        self::assertSame(
            ['code=V01 | line=— | message=first', 'code=V03 | line=12 | message=second'],
            $this->lines,
        );
    }

    public function testTableAlignsMultibyteCells(): void
    {
        // Regression: str_pad() counted "—" as 3 bytes and shifted the row.
        (new OutputRenderer())->render($this->inspection(), $this->outputDouble(), OutputFormat::Table);

        $first  = $this->lineContaining('first');
        $second = $this->lineContaining('second');

        self::assertSame(
            mb_strwidth(mb_substr($first, 0, (int) mb_strpos($first, 'first'))),
            mb_strwidth(mb_substr($second, 0, (int) mb_strpos($second, 'second'))),
        );
    }

    public function testMachineFormatClassification(): void
    {
        $renderer = new OutputRenderer();

        self::assertTrue($renderer->isMachineFormat(OutputFormat::Json));
        self::assertTrue($renderer->isMachineFormat(OutputFormat::Plain));
        self::assertFalse($renderer->isMachineFormat(OutputFormat::Table));
        self::assertFalse($renderer->isMachineFormat(OutputFormat::Tree));
    }

    private function inspection(): InspectionResult
    {
        return new InspectionResult(
            componentName: 'Test',
            rows: [
                ['code' => 'V01', 'line' => '—', 'message' => 'first'],
                ['code' => 'V03', 'line' => '12', 'message' => 'second'],
            ],
            summary: ['total' => 2, 'ast_engine' => false],
        );
    }

    private function outputDouble(): Output
    {
        $output = $this->createMock(Output::class);
        $output->method('writeln')->willReturnCallback(
            function (mixed $message = ''): void {
                $this->lines[] = (string) $message;
            },
        );

        return $output;
    }

    private function lineContaining(string $needle): string
    {
        foreach ($this->lines as $line) {
            if (str_contains($line, $needle)) {
                return $line;
            }
        }

        self::fail("No rendered line contains '{$needle}'");
    }
}
