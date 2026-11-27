<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Template;

use KaririCode\Cli\Enum\OutputFormat;
use KaririCode\Cli\Template\OutputRenderer;
use KaririCode\Cli\Tests\Support\RecordingOutput;
use KaririCode\Cli\ValueObject\InspectionResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Every line of every format, tags included: column widths count display
 * columns (multibyte), the header pads, the rule is 70 columns, the tree
 * connectors change on the last row, booleans read yes/no and null an em
 * dash.
 */
#[CoversClass(OutputRenderer::class)]
final class OutputRendererGoldenTest extends TestCase
{
    private const string RULE = '  ──────────────────────────────────────────────────────────────────────';

    public function testTable(): void
    {
        self::assertSame([
            ...self::header(),
            '<info>  NAME  COUNT  OK   NOTE  </info>',
            '  ────────────────────────',
            '  ação  3      yes',
            '  b     12     no   x',
            '',
        ], self::render(OutputFormat::Table));
    }

    public function testTree(): void
    {
        self::assertSame([
            ...self::header(),
            '  ├─ <info>ação</info>',
            '  │     <comment>count:</comment> 3',
            '  │     <comment>ok:</comment> yes',
            '  │     <comment>note:</comment> —',
            '  └─ <info>b</info>',
            '        <comment>count:</comment> 12',
            '        <comment>ok:</comment> no',
            '        <comment>note:</comment> x',
            '',
        ], self::render(OutputFormat::Tree));
    }

    public function testJsonKeepsTypesAndUnicode(): void
    {
        self::assertSame([<<<'JSON'
            {
                "component": "Demo",
                "summary": {
                    "total_rows": 2,
                    "strict": true,
                    "missing": null,
                    "ratio": 0.5
                },
                "rows": [
                    {
                        "name": "ação",
                        "count": 3,
                        "ok": true,
                        "note": null
                    },
                    {
                        "name": "b",
                        "count": 12,
                        "ok": false,
                        "note": "x"
                    }
                ]
            }
            JSON], self::render(OutputFormat::Json));
    }

    public function testPlain(): void
    {
        self::assertSame(['name=ação | count=3 | ok=yes | note=—', 'name=b | count=12 | ok=no | note=x'], self::render(OutputFormat::Plain));
    }

    public function testEmptyResultsSayNoData(): void
    {
        foreach ([OutputFormat::Table, OutputFormat::Tree] as $format) {
            $output = new RecordingOutput();
            (new OutputRenderer())->render(new InspectionResult('Empty', []), $output, $format);

            self::assertSame(['', '<comment>  Empty</comment>', self::RULE, self::RULE, '', '  <comment>(no data)</comment>', ''], $output->lines, $format->name);
        }
    }

    public function testOnlyJsonAndPlainAreMachineFormats(): void
    {
        $renderer = new OutputRenderer();

        self::assertSame([true, true, false, false], array_map($renderer->isMachineFormat(...), [OutputFormat::Json, OutputFormat::Plain, OutputFormat::Table, OutputFormat::Tree]));
    }

    /** @return list<string> */
    private static function render(OutputFormat $format): array
    {
        $output = new RecordingOutput();
        (new OutputRenderer())->render(new InspectionResult(
            componentName: 'Demo',
            rows: [
                ['name' => 'ação', 'count' => 3, 'ok' => true, 'note' => null],
                ['name' => 'b', 'count' => 12, 'ok' => false, 'note' => 'x'],
            ],
            summary: ['total_rows' => 2, 'strict' => true, 'missing' => null, 'ratio' => 0.5],
        ), $output, $format);

        return $output->lines;
    }

    /** @return list<string> */
    private static function header(): array
    {
        return [
            '',
            '<comment>  Demo</comment>',
            self::RULE,
            '  <info>Total rows          </info> 2',
            '  <info>Strict              </info> yes',
            '  <info>Missing             </info> —',
            '  <info>Ratio               </info> 0.5',
            self::RULE,
            '',
        ];
    }
}
