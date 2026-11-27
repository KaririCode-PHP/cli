<?php

declare(strict_types=1);

namespace KaririCode\Cli\Template;

use KaririCode\Cli\Enum\OutputFormat;
use KaririCode\Cli\ValueObject\InspectionResult;
use KaririCode\Console\Contract\Output;

/**
 * Renders an InspectionResult to a Console Output in the requested format.
 *
 * All rendering is isolated here so inspectors remain pure (ARFA 1.3 P4).
 * The renderer never modifies the result, only reads it.
 *
 * Machine formats (json, plain) emit data only — no header, summary
 * block or footer — so the output can be piped into jq, grep or
 * a CI parser. Table widths use display columns (mb_strwidth), not bytes,
 * so cells containing "—", "✓" or accented text stay aligned.
 *
 * ARFA 1.3: P4. Spec V4.3.3: stateless service.
 *
 * Preconditions: rows share the keys of the first row.
 * Thread-safety: stateless; safe to share.
 * Side effects: writes to Output only.
 *
 * Example:
 *   $renderer->render($result, $output, OutputFormat::Json);
 *
 * References: ADR-004.
 * @see https://www.php.net/manual/en/function.mb-strwidth.php
 */
final class OutputRenderer
{
    private const int RULE_WIDTH = 70;

    // ── Public API ───────────────────────────────────────────────────────

    public function render(InspectionResult $result, Output $output, OutputFormat $format): void
    {
        match ($format) {
            OutputFormat::Json  => $this->renderJson($result, $output),
            OutputFormat::Plain => $this->renderPlain($result, $output),
            OutputFormat::Table => $this->renderDecorated($result, $output, $this->renderTable(...)),
            OutputFormat::Tree  => $this->renderDecorated($result, $output, $this->renderTree(...)),
        };
    }

    /** True for formats whose output must stay machine-parseable. */
    public function isMachineFormat(OutputFormat $format): bool
    {
        return $format === OutputFormat::Json || $format === OutputFormat::Plain;
    }

    // ── Human formats ────────────────────────────────────────────────────

    /** @param \Closure(InspectionResult, Output): void $body */
    private function renderDecorated(InspectionResult $result, Output $output, \Closure $body): void
    {
        $output->writeln('');
        $output->writeln("<comment>  {$result->componentName}</comment>");
        $output->writeln('  ' . str_repeat('─', self::RULE_WIDTH));

        foreach ($result->summary as $key => $value) {
            $label = $this->pad(str_replace('_', ' ', ucfirst((string) $key)), 20);
            $output->writeln("  <info>{$label}</info> {$this->stringify($value)}");
        }

        $output->writeln('  ' . str_repeat('─', self::RULE_WIDTH));
        $output->writeln('');

        $body($result, $output);

        $output->writeln('');
    }

    private function renderTable(InspectionResult $result, Output $output): void
    {
        if ($result->isEmpty()) {
            $output->writeln('  <comment>(no data)</comment>');

            return;
        }

        $columns = array_keys($result->rows[0]);
        $widths  = $this->columnWidths($result->rows, $columns);

        $header = '  ';
        foreach ($columns as $column) {
            $header .= $this->pad(strtoupper((string) $column), $widths[$column] + 2);
        }

        $output->writeln("<info>{$header}</info>");
        $output->writeln('  ' . str_repeat('─', array_sum($widths) + count($columns) * 2));

        foreach ($result->rows as $row) {
            $line = '  ';
            foreach ($columns as $column) {
                $line .= $this->pad($this->stringify($row[$column] ?? ''), $widths[$column] + 2);
            }
            $output->writeln(rtrim($line));
        }
    }

    private function renderTree(InspectionResult $result, Output $output): void
    {
        if ($result->isEmpty()) {
            $output->writeln('  <comment>(no data)</comment>');

            return;
        }

        $last = count($result->rows) - 1;

        foreach ($result->rows as $i => $row) {
            $connector = $i < $last ? '├─' : '└─';
            $branch    = $i < $last ? '│  ' : '   ';
            $label     = $this->stringify(reset($row));

            $output->writeln("  {$connector} <info>{$label}</info>");

            foreach (array_slice($row, 1) as $key => $value) {
                $output->writeln("  {$branch}   <comment>{$key}:</comment> {$this->stringify($value)}");
            }
        }
    }

    // ── Machine formats ──────────────────────────────────────────────────

    private function renderJson(InspectionResult $result, Output $output): void
    {
        $payload = [
            'component'   => $result->componentName,
            'summary'     => $result->summary,
            'rows'        => $result->rows
        ];

        $output->writeln(json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        ));
    }

    private function renderPlain(InspectionResult $result, Output $output): void
    {
        foreach ($result->rows as $row) {
            $pairs = [];
            foreach ($row as $key => $value) {
                $pairs[] = "{$key}={$this->stringify($value)}";
            }
            $output->writeln(implode(' | ', $pairs));
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * @param  list<array<string,mixed>> $rows
     * @param  list<string>              $columns
     * @return array<string,int>
     */
    private function columnWidths(array $rows, array $columns): array
    {
        $widths = [];

        foreach ($columns as $column) {
            $widths[$column] = mb_strwidth((string) $column);
        }

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $widths[$column] = max($widths[$column], mb_strwidth($this->stringify($row[$column] ?? '')));
            }
        }

        return $widths;
    }

    /** Right-pads to a display width (str_pad counts bytes, not columns). */
    private function pad(string $text, int $width): string
    {
        return $text . str_repeat(' ', max(0, $width - mb_strwidth($text)));
    }

    /** Scalar-safe text for summary/cell values (arrays and bools included). */
    private function stringify(mixed $value): string
    {
        return match (true) {
            is_bool($value)   => $value ? 'yes' : 'no',
            $value === null   => '—',
            is_scalar($value) => (string) $value,
            default           => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }
}
