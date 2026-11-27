<?php

declare(strict_types=1);

namespace KaririCode\Cli\Application;

use KaririCode\Cli\Enum\WriteStatus;
use KaririCode\Cli\Exception\ScaffoldFailureException;
use KaririCode\Cli\ValueObject\GeneratedFile;

/**
 * Writes generated files to disk or reports them in dry-run mode.
 *
 * All filesystem I/O is isolated here so generators remain pure
 * (ARFA 1.3 P4 — protocol agnosticism). Tests inject FileWriter
 * with dryRun=true to assert content without touching the FS.
 *
 * writeAll() runs a collision pre-flight over the whole batch before the
 * first byte is written: a multi-file generator (make:repository emits an
 * interface and an implementation) either writes every file or none.
 *
 * ARFA 1.3: P4 (generators stay pure). Spec V4.3.3: stateless service.
 *
 * Preconditions: target directories are writable.
 * Thread-safety: stateless; LOCK_EX serialises each single write, not a batch.
 * Side effects: creates directories and files; nothing in dry-run mode.
 *
 * Example:
 *   (new FileWriter(dryRun: true))->writeAll($files);
 *
 * References: ADR-005.
 * @see https://www.php.net/manual/en/function.file-put-contents.php
 */
final class FileWriter
{
    public function __construct(
        private readonly bool $dryRun = false,
        private readonly bool $force = false,
    ) {}

    // ── Public API ───────────────────────────────────────────────────────

    /**
     * Writes $file to disk (or returns a dry-run report).
     *
     * @throws ScaffoldFailureException 8640 when the file exists and force is off,
     *                                  8641 when the directory or file cannot be written
     */
    public function write(GeneratedFile $file): WriteOutcome
    {
        if ($this->dryRun) {
            return new WriteOutcome(
                path: $file->path,
                status: WriteStatus::DryRun,
                content: $file->content,
                lineCount: $file->lineCount(),
            );
        }

        $existed = file_exists($file->path);

        if ($existed && !$this->force) {
            throw ScaffoldFailureException::fileExists($file->path);
        }

        $directory = $file->directory();

        if (!is_dir($directory) && !mkdir($directory, 0755, recursive: true) && !is_dir($directory)) {
            throw ScaffoldFailureException::writeFailed($file->path, "cannot create directory '{$directory}'");
        }

        if (file_put_contents($file->path, $file->content, LOCK_EX) === false) {
            throw ScaffoldFailureException::writeFailed($file->path, 'file_put_contents returned false');
        }

        return new WriteOutcome(
            path: $file->path,
            status: $existed ? WriteStatus::Updated : WriteStatus::Created,
            content: null,
            lineCount: $file->lineCount(),
        );
    }

    /**
     * Writes a batch with an all-or-nothing collision pre-flight.
     *
     * @param  list<GeneratedFile> $files
     * @return list<WriteOutcome>
     *
     * @throws ScaffoldFailureException 8640 before any write when one target already exists
     */
    public function writeAll(array $files): array
    {
        if (!$this->dryRun && !$this->force) {
            foreach ($files as $file) {
                if (file_exists($file->path)) {
                    throw ScaffoldFailureException::fileExists($file->path);
                }
            }
        }

        return array_map($this->write(...), $files);
    }
}
