<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Support;

use KaririCode\Console\Contract\Output;
use KaririCode\Console\Enum\Verbosity;

/** Keeps every line as written, tags included, so a test can pin exact output. */
final class RecordingOutput implements Output
{
    /** @var list<string> */
    public array $lines = [];

    public Verbosity $verbosity = Verbosity::Normal;

    public bool $decorated = false;

    public Output $errorOutput {
        get => $this;
    }

    #[\Override]
    public function write(string $message, Verbosity $verbosity = Verbosity::Normal): void
    {
        $this->lines[] = $message;
    }

    #[\Override]
    public function writeln(string $message = '', Verbosity $verbosity = Verbosity::Normal): void
    {
        $this->lines[] = $message;
    }

    #[\Override]
    public function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->lines[] = '';
        }
    }
}
