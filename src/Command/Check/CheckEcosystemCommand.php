<?php

declare(strict_types=1);

namespace KaririCode\Cli\Command\Check;

use KaririCode\Console\Attribute\AsCommand;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\ValueObject\OptionDescriptor;

/**
 * Compares installed KaririCode packages against the V4.0 baseline.
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
#[AsCommand(name: 'check:ecosystem', description: 'Verify all KaririCode packages are V4.0 / ARFA 1.3 compatible')]
final class CheckEcosystemCommand implements Command
{
    /** Minimum version this CLI expects of each KaririCode package. */
    private const array MINIMUM_VERSIONS = [
        'kariricode/contract'             => '4.0.0',
        'kariricode/processor-pipeline'   => '4.0.0',
        'kariricode/property-inspector'   => '4.0.0',
        'kariricode/class-discovery'      => '3.2.0',
        'kariricode/dependency-injection' => '4.0.0',
        'kariricode/cache'                => '4.0.0',
        'kariricode/logging'              => '4.0.0',
        'kariricode/validator'            => '4.0.0',
        'kariricode/sanitizer'            => '4.0.0',
        'kariricode/event-dispatcher'     => '4.0.0',
        'kariricode/console'              => '4.1.0',
        'kariricode/parser'               => '4.0.0',
    ];

    public function configure(InputDefinition $definition): void
    {
        $definition->addOption(new OptionDescriptor(
            name: 'json',
            description: 'Output as JSON',
            acceptsValue: false,
            default: false,
        ));
    }

    public function execute(Input $input, Output $output): int
    {
        $lock = $this->readLock($output);

        if ($lock === null) {
            return 1;
        }

        $results = $this->compare($this->indexPackages([
            ...(is_array($lock['packages'] ?? null) ? $lock['packages'] : []),
            ...(is_array($lock['packages-dev'] ?? null) ? $lock['packages-dev'] : []),
        ]));
        $failed = count(array_filter($results, static fn(array $row): bool => $row['status'] === 'OUTDATED'));

        if ((bool) $input->getOption('json')) {
            $output->writeln(json_encode(
                $results,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            return $failed > 0 ? 1 : 0;
        }

        $this->renderTable($results, $output);
        $output->writeln($failed === 0
            ? '<info>✓ Every installed KaririCode package meets the minimum version this CLI expects.</info>'
            : "<error>✗ {$failed} package(s) below the minimum version this CLI expects.</error>");

        return $failed === 0 ? 0 : 1;
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
            $version === null => 'NOT INSTALLED',
            str_starts_with($version, 'dev-') || str_ends_with($version, '-dev') => 'DEV',
            version_compare($version, $minimum, '>=') => 'OK',
            default => 'OUTDATED',
        };
    }

    /**
     * @param  list<array<string,mixed>> $packages
     * @return array<string,string> package → version
     */
    private function indexPackages(array $packages): array
    {
        $index = [];

        foreach ($packages as $package) {
            $name = (string) ($package['name'] ?? '');

            if ($name !== '') {
                $index[$name] = ltrim((string) ($package['version'] ?? ''), 'v');
            }
        }

        return $index;
    }

    /** @param list<array{package:string,installed:string,minimum:string,status:string}> $results */
    private function renderTable(array $results, Output $output): void
    {
        $output->writeln('');
        $output->writeln('  <comment>KaririCode Ecosystem — V4.0 Baseline Check</comment>');
        $output->writeln('  ' . str_repeat('─', 70));
        $output->writeln(sprintf('  %-40s %-12s %-12s %s', 'PACKAGE', 'INSTALLED', 'MINIMUM', 'STATUS'));
        $output->writeln('  ' . str_repeat('─', 70));

        foreach ($results as $row) {
            $tag = match ($row['status']) {
                'OK'       => 'info',
                'OUTDATED' => 'error',
                default    => 'comment',
            };

            $output->writeln(sprintf(
                '  %-40s %-12s %-12s %s',
                $row['package'],
                $row['installed'],
                $row['minimum'],
                "<{$tag}>{$row['status']}</{$tag}>",
            ));
        }

        $output->writeln('');
    }
}
