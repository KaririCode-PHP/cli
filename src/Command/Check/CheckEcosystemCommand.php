<?php

declare(strict_types=1);

namespace KaririCode\Cli\Command\Check;

use KaririCode\Cli\Application\InputValue;
use KaririCode\Console\Attribute\AsCommand;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\Enum\ExitCode;
use KaririCode\Console\ValueObject\OptionDescriptor;

/**
 * Compares installed KaririCode packages against the lowest release of
 * each one that the Spec V4.3.3 / ARFA 1.3 line works with
 * (MINIMUM_VERSIONS).
 *
 * Reads composer.lock from the working directory — both "packages" and
 * "packages-dev", since the CLI and the Devkit are usually installed as
 * dev dependencies. Branch installs (dev-main, 4.1.x-dev) cannot be
 * compared by version_compare() and are reported as DEV, not OUTDATED.
 *
 * Exit codes: 0 = baseline met, 1 = at least one package OUTDATED or
 * composer.lock missing/invalid.
 *
 * ARFA 1.3: P4. Spec V4.3.3: Console command, final and stateless.
 *
 * Preconditions: run from the project root after composer install.
 * Thread-safety: stateless between dispatches.
 * Side effects: reads composer.lock and writes to Output.
 *
 * Example:
 *   kcli check:ecosystem --json
 *
 * References: SPEC-001 §5.
 * @see https://getcomposer.org/doc/01-basic-usage.md#commit-your-composer-lock-file-to-version-control
 */
#[AsCommand(name: 'check:ecosystem', description: 'Compare installed KaririCode packages with the Spec V4.3.3 / ARFA 1.3 line')]
final class CheckEcosystemCommand implements Command
{
    public string $name {
        get => 'check:ecosystem';
    }

    public string $description {
        get => 'Compare installed KaririCode packages with the Spec V4.3.3 / ARFA 1.3 line';
    }

    /**
     * Lowest Packagist release of each package that the Spec V4.3.3 /
     * ARFA 1.3 line works with (contract 2.12.0: console 4.6 and
     * property-inspector 3.0 require it). A package joins the list when its
     * release for the line is published; until then it is not judged.
     */
    private const array MINIMUM_VERSIONS = [
        'kariricode/contract'           => '2.12.0',
        'kariricode/exception'          => '2.0.0',
        'kariricode/class-discovery'    => '2.0.0',
        'kariricode/property-inspector' => '3.0.0',
        'kariricode/processor-pipeline' => '3.0.0',
        'kariricode/cache'              => '4.1.0',
        'kariricode/console'            => '4.6.0',
        'kariricode/router'             => '4.1.0',
        'kariricode/database'           => '4.4.0',
    ];

    #[\Override]
    public function configure(InputDefinition $definition): void
    {
        $definition->addOption(new OptionDescriptor(
            name: 'json',
            description: 'Output as JSON',
            acceptsValue: false,
            default: false,
        ));
    }

    #[\Override]
    public function execute(Input $input, Output $output): ExitCode
    {
        $lock = $this->readLock($output);

        if ($lock === null) {
            return ExitCode::Failure;
        }

        $results = $this->compare(self::installedVersions($lock));
        $failed  = count(array_filter($results, static fn(array $row): bool => $row['status'] === 'OUTDATED'));

        if (InputValue::flag($input, 'json')) {
            $output->writeln(json_encode(
                $results,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            return $failed > 0 ? ExitCode::Failure : ExitCode::Success;
        }

        $this->renderTable($results, $output);
        $output->writeln($failed === 0
            ? '<info>✓ Every installed KaririCode package meets the minimum version this CLI expects.</info>'
            : "<error>✗ {$failed} package(s) below the minimum version this CLI expects.</error>");

        return $failed === 0 ? ExitCode::Success : ExitCode::Failure;
    }

    /** @return array<mixed>|null null once the problem has been reported */
    private function readLock(Output $output): ?array
    {
        $lockFile = (string) getcwd() . '/composer.lock';

        if (!is_file($lockFile)) {
            $output->writeln('<error>composer.lock not found — run from the project root after composer install.</error>');

            return null;
        }

        try {
            $lock = json_decode((string) file_get_contents($lockFile), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $output->writeln("<error>composer.lock is not valid JSON: {$e->getMessage()}</error>");

            return null;
        }

        if (!is_array($lock)) {
            $output->writeln('<error>composer.lock has an unexpected structure.</error>');

            return null;
        }

        return $lock;
    }

    /**
     * @param  array<string, string> $installed
     * @return list<array{package: string, installed: string, minimum: string, status: string}>
     */
    private function compare(array $installed): array
    {
        $results = [];

        foreach (self::MINIMUM_VERSIONS as $package => $minimum) {
            $version   = $installed[$package] ?? null;
            $results[] = [
                'package'   => $package,
                'installed' => $version ?? '—',
                'minimum'   => $minimum,
                'status'    => $this->evaluate($version, $minimum),
            ];
        }

        return $results;
    }

    // ── Private ──────────────────────────────────────────────────────────

    /** OK | OUTDATED | DEV (branch install, not comparable) | NOT INSTALLED (optional) */
    private function evaluate(?string $version, string $minimum): string
    {
        return match (true) {
            $version === null                                                    => 'NOT INSTALLED',
            str_starts_with($version, 'dev-') || str_ends_with($version, '-dev') => 'DEV',
            version_compare($version, $minimum, '>=')                            => 'OK',
            default                                                              => 'OUTDATED',
        };
    }

    /**
     * Package name → installed version (without a leading "v"), from the
     * packages and packages-dev lists of the lock. Entries of another shape
     * are skipped rather than cast.
     *
     * @param  array<mixed> $lock
     * @return array<string, string>
     */
    private static function installedVersions(array $lock): array
    {
        $index = [];

        foreach (['packages', 'packages-dev'] as $section) {
            foreach (self::entries($lock[$section] ?? null) as $package) {
                if (isset($package['name'], $package['version']) && is_string($package['name']) && is_string($package['version']) && $package['name'] !== '') {
                    $index[$package['name']] = ltrim($package['version'], 'v');
                }
            }
        }

        return $index;
    }

    /** @return list<array<mixed>> */
    private static function entries(mixed $list): array
    {
        if (!is_array($list)) {
            return [];
        }

        $entries = [];

        foreach (array_keys($list) as $key) {
            if (is_array($list[$key])) {
                $entries[] = $list[$key];
            }
        }

        return $entries;
    }

    /** @param list<array{package:string,installed:string,minimum:string,status:string}> $results */
    private function renderTable(array $results, Output $output): void
    {
        $output->writeln('');
        $output->writeln('  <comment>KaririCode Ecosystem — Spec V4.3.3 / ARFA 1.3 line</comment>');
        $output->writeln('  ' . str_repeat('─', 70));
        $output->writeln(sprintf('  %-40s %-12s %-12s %s', 'PACKAGE', 'INSTALLED', 'MINIMUM', 'STATUS'));
        $output->writeln('  ' . str_repeat('─', 70));

        foreach ($results as $row) {
            $tag = match ($row['status']) {
                'OK'       => 'info',
                'OUTDATED' => 'error',
                default    => 'comment',
            };

            // mb_str_pad: "—" is one column but three bytes, and sprintf()
            // pads by bytes, which shifted the MINIMUM column.
            $output->writeln('  ' . mb_str_pad($row['package'], 40) . ' ' . mb_str_pad($row['installed'], 12)
                . ' ' . mb_str_pad($row['minimum'], 12) . " <{$tag}>{$row['status']}</{$tag}>");
        }

        $output->writeln('');
    }
}
