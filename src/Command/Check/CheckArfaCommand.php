<?php

declare(strict_types=1);

namespace KaririCode\Cli\Command\Check;

use KaririCode\Cli\Application\InputValue;
use KaririCode\Cli\Contract\ComplianceEngine;
use KaririCode\Cli\Enum\OutputFormat;
use KaririCode\Cli\Enum\ViolationSeverity;
use KaririCode\Cli\Inspector\ArfaComplianceInspector;
use KaririCode\Cli\Inspector\RegexComplianceEngine;
use KaririCode\Cli\Template\OutputRenderer;
use KaririCode\Cli\ValueObject\InspectionResult;
use KaririCode\Console\Attribute\AsCommand;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\Enum\ExitCode;
use KaririCode\Console\ValueObject\OptionDescriptor;

/**
 * Scans a PHP source tree for ARFA 1.3 / Spec V4.3.3 violations.
 *
 * This command has NO equivalent in Laravel Artisan, Symfony Console,
 * or any other PHP framework CLI. It is the on-ramp to the ARFA 1.3
 * patent-pending specification compliance check.
 *
 * Exit code semantics (compatible with CI pipelines):
 *   0  — compliant, or only P1 violations without --fail-on-p1
 *   1  — P0 violations found (build-breaking) or --path is not a directory
 *   2  — P1 violations found and --fail-on-p1 is set
 *
 * ARFA 1.3: P4. Spec V4.3.3: Console command, final and stateless.
 *
 * Preconditions: --path names a directory.
 * Thread-safety: stateless between dispatches.
 * Side effects: reads the scanned tree and writes to Output; exit codes in
 * SPEC-001 §5.
 *
 * Example:
 *   kcli check:arfa --path=src --format=json
 *
 * References: ADR-004.
 * @see https://www.pcre.org/current/doc/html/pcre2pattern.html
 */
#[AsCommand(name: 'check:arfa', description: 'Scan source tree for ARFA 1.3 / Spec V4.3.3 violations')]
final class CheckArfaCommand implements Command
{
    public string $name {
        get => 'check:arfa';
    }

    public string $description {
        get => 'Scan source tree for ARFA 1.3 / Spec V4.3.3 violations';
    }

    public function __construct(
        private readonly OutputRenderer $renderer,
        private readonly ComplianceEngine $engine = new RegexComplianceEngine(),
    ) {}

    #[\Override]
    public function configure(InputDefinition $definition): void
    {
        $definition->addOption(new OptionDescriptor(
            name: 'path',
            description: 'Source directory to scan (default: src)',
            shortcut: 'p',
            default: 'src',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'format',
            description: 'Output format: table, tree, json, plain',
            default: 'table',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'severity',
            description: 'Minimum severity to list: P1 (all, default) or P0 (build-breaking only)',
            default: 'P1',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'fail-on-p1',
            description: 'Exit 2 when only P1 violations are found (default: only P0 fails the build)',
            acceptsValue: false,
            default: false,
        ));
    }

    #[\Override]
    public function execute(Input $input, Output $output): ExitCode
    {
        $path      = InputValue::text($input, 'path', 'src');
        $format    = OutputFormat::fromString(InputValue::text($input, 'format', 'table'));
        $threshold = ViolationSeverity::fromLabel(InputValue::text($input, 'severity', 'P1'));
        $failOnP1  = InputValue::flag($input, 'fail-on-p1');

        if (!is_dir($path)) {
            $output->writeln("<error>✗ Directory not found: {$path}</error>");

            return ExitCode::Failure;
        }

        $result = (new ArfaComplianceInspector(sourcePath: $path, engine: $this->engine))->inspect();

        // Threshold P1 lists every violation; P0 lists only build-breaking ones.
        $filtered = new InspectionResult(
            componentName: $result->componentName,
            rows: array_values(array_filter(
                $result->rows,
                fn(array $row): bool => $threshold->includes(
                    ViolationSeverity::fromLabel((string) ($row['severity'] ?? 'P1')),
                ),
            )),
            summary: $result->summary,
        );

        $this->renderer->render($filtered, $output, $format);

        $p0 = (int) ($result->summary['p0_violations'] ?? 0);
        $p1 = (int) ($result->summary['p1_violations'] ?? 0);

        // ExitCode::Invalid is status 2, the status check:arfa documents for
        // "P1 violations with --fail-on-p1" (README); console 4.6 has no
        // other way to return 2.
        $exitCode = match (true) {
            $p0 > 0              => ExitCode::Failure,
            $p1 > 0 && $failOnP1 => ExitCode::Invalid,
            default              => ExitCode::Success,
        };

        // Machine formats stay parseable: the exit code carries the verdict.
        if ($this->renderer->isMachineFormat($format)) {
            return $exitCode;
        }

        if ($p0 > 0) {
            $output->writeln("<error>✗ {$p0} P0 violation(s) — build will fail</error>");
        } elseif ($p1 > 0) {
            $output->writeln("<comment>⚠ {$p1} P1 violation(s) — review recommended</comment>");
        } else {
            $output->writeln('<info>✓ Fully compliant with ARFA 1.3 / Spec V4.3.3</info>');
        }

        return $exitCode;
    }
}
