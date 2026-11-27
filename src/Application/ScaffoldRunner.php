<?php

declare(strict_types=1);

namespace KaririCode\Cli\Application;

use KaririCode\Cli\Generator\GeneratorRegistry;
use KaririCode\Cli\ValueObject\GeneratorContext;
use KaririCode\Console\Contract\Output;
use KaririCode\Contract\Exception\ExceptionInterface;

/**
 * Shared generate → write → report pipeline for every make:* command.
 *
 * Commands stay thin: they translate Input into a validated
 * GeneratorContext and delegate here. Composition instead of an abstract
 * base command keeps ADR-001 (zero inheritance) intact, and a single
 * pipeline means dry-run output, status lines and error codes are
 * identical across all generators.
 *
 * ARFA 1.3: P4. Spec V4.3.3: stateless service, composition instead of a base
 * command (ADR-001).
 *
 * Preconditions: the context is valid and its target has a registered generator.
 * Thread-safety: holds only the registry.
 * Side effects: writes files through FileWriter and lines to Output.
 *
 * Example:
 *   (new ScaffoldRunner($registry))->run($context, $output);
 *
 * References: ADR-008.
 * @see https://www.php-fig.org/psr/psr-4/
 */
final class ScaffoldRunner
{
    public function __construct(
        private readonly GeneratorRegistry $registry,
    ) {}

    /**
     * Normalises a raw class-name argument: trims, upper-cases the first
     * letter and appends $suffix unless already present (case-insensitive,
     * so "UserDPO" is not turned into "UserDPODpo").
     */
    public static function className(string $raw, string $suffix = ''): string
    {
        $name = ucfirst(trim($raw));

        if ($suffix === '' || str_ends_with(strtolower($name), strtolower($suffix))) {
            return $name;
        }

        return $name . $suffix;
    }

    /**
     * Resolves --path against the working directory. Absolute paths
     * (POSIX "/..." or a Windows drive such as "C:/...") are used as given; they were
     * previously glued onto getcwd(), producing "<cwd>/tmp/out".
     */
    public static function outputPath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return rtrim($path, '/\\');
        }

        return rtrim((string) getcwd(), '/\\') . '/' . ltrim($path, '/\\');
    }

    /**
     * @return list<WriteOutcome>
     *
     * @throws ExceptionInterface on unknown target, existing files or write failures
     */
    public function run(GeneratorContext $context, Output $output): array
    {
        $files    = $this->registry->get($context->target)->generate($context);
        $writer   = new FileWriter(dryRun: $context->dryRun, force: $context->force);
        $outcomes = $writer->writeAll($files);

        foreach ($outcomes as $outcome) {
            if ($outcome->isDryRun()) {
                $output->writeln('');
                $output->writeln(
                    "<comment>── DRY-RUN: {$outcome->path} ({$outcome->lineCount} lines) ──</comment>",
                );
                $output->writeln($outcome->content ?? '');

                continue;
            }

            $output->writeln(
                "<info>  {$outcome->status->label()}</info>  {$outcome->path} ({$outcome->lineCount} lines)",
            );
        }

        if (!$context->dryRun) {
            $output->writeln('');
            $output->writeln("<info>✓</info> {$context->target->label()}: {$context->fullyQualifiedClassName()}");
        }

        return $outcomes;
    }
}
