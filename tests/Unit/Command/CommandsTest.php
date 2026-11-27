<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Command;

use KaririCode\Cli\Command\Check\CheckArfaCommand;
use KaririCode\Cli\Command\Check\CheckEcosystemCommand;
use KaririCode\Cli\Command\Inspect\InspectAstCommand;
use KaririCode\Cli\Command\Inspect\InspectContainerCommand;
use KaririCode\Cli\Command\Inspect\InspectDiagnosticsCommand;
use KaririCode\Cli\Command\Make\MakeEntityCommand;
use KaririCode\Cli\Integration\CliServiceProvider;
use KaririCode\Cli\Tests\Support\RecordingOutput;
use KaririCode\Console\Core\Application;
use KaririCode\Console\Enum\ExitCode;
use KaririCode\Console\Input\ArrayInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The commands run through a real console Application: what they print and
 * the ExitCode they return, including status 2 (Invalid) where the CLI
 * documents it.
 */
#[CoversClass(CheckArfaCommand::class)]
#[CoversClass(CheckEcosystemCommand::class)]
#[CoversClass(MakeEntityCommand::class)]
#[CoversClass(InspectAstCommand::class)]
#[CoversClass(InspectDiagnosticsCommand::class)]
#[CoversClass(InspectContainerCommand::class)]
final class CommandsTest extends TestCase
{
    private const string DOC = "/**\n * Role.\n *\n * Preconditions: none.\n * Thread-safety: n/a.\n * Side effects: none.\n *\n * Example:\n *   x();\n *\n * References: none.\n */\n";

    private string $dir;

    private Application $application;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/kcli-commands-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
        $this->application = CliServiceProvider::buildApplication();
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function testCheckArfaFailsOnP0AndPassesWithOnlyP1(): void
    {
        file_put_contents($this->dir . '/A.php', "<?php\n\n" . self::DOC . "final class A {}\n");
        self::assertSame(ExitCode::Failure, $this->runCommand('check:arfa', ['path' => $this->dir])->exitCode);
        self::assertStringContainsString('P0 violation(s)', implode("\n", $this->output->lines));

        file_put_contents($this->dir . '/A.php', "<?php\n\ndeclare(strict_types=1);\n\n" . self::DOC . "final class A\n{\n    private const LIMIT = 3;\n}\n");
        self::assertSame(ExitCode::Success, $this->runCommand('check:arfa', ['path' => $this->dir])->exitCode, 'P1 alone passes');
        self::assertSame(ExitCode::Invalid, $this->runCommand('check:arfa', ['path' => $this->dir, 'fail-on-p1' => true])->exitCode, 'status 2 with --fail-on-p1');
        self::assertSame(ExitCode::Success, $this->runCommand('check:arfa', ['path' => $this->dir, 'severity' => 'P0'])->exitCode);

        file_put_contents($this->dir . '/A.php', "<?php\n\ndeclare(strict_types=1);\n\n" . self::DOC . "final class A {}\n");
        self::assertSame(ExitCode::Success, $this->runCommand('check:arfa', ['path' => $this->dir])->exitCode);
        self::assertStringContainsString('Fully compliant', implode("\n", $this->output->lines));
    }

    public function testCheckArfaKeepsMachineFormatsParseable(): void
    {
        file_put_contents($this->dir . '/A.php', "<?php\n\n" . self::DOC . "final class A {}\n");

        $result = $this->runCommand('check:arfa', ['path' => $this->dir, 'format' => 'json']);

        self::assertSame(ExitCode::Failure, $result->exitCode);
        self::assertCount(1, $this->output->lines);
        self::assertTrue(json_validate($this->output->lines[0]));
    }

    public function testCheckArfaRejectsAMissingDirectory(): void
    {
        self::assertSame(ExitCode::Failure, $this->runCommand('check:arfa', ['path' => $this->dir . '/missing'])->exitCode);
        self::assertStringContainsString('Directory not found', implode("\n", $this->output->lines));
    }

    public function testCheckEcosystemJudgesTheLockAgainstTheLine(): void
    {
        $this->writeLock(
            [['name' => 'kariricode/contract', 'version' => 'v2.12.1'], ['name' => 'kariricode/console', 'version' => 'v4.5.0'], ['name' => 'kariricode/cache', 'version' => 'dev-main'], ['version' => '1.0.0'], 'not a package'],
            [['name' => 'kariricode/processor-pipeline', 'version' => '3.0.0']],
        );

        $result = $this->runInDir('check:ecosystem', ['json' => true]);
        $rows   = json_decode($this->output->lines[0], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($rows);
        $status = array_column($rows, 'status', 'package');

        self::assertSame(ExitCode::Failure, $result->exitCode, 'console 4.5.0 is below 4.6.0');
        self::assertSame('OK', $status['kariricode/contract']);
        self::assertSame('OUTDATED', $status['kariricode/console']);
        self::assertSame('DEV', $status['kariricode/cache']);
        self::assertSame('OK', $status['kariricode/processor-pipeline'], 'packages-dev is read');
        self::assertSame('NOT INSTALLED', $status['kariricode/router']);
        self::assertSame(['package' => 'kariricode/contract', 'installed' => '2.12.1', 'minimum' => '2.12.0', 'status' => 'OK'], $rows[0]);

        $this->writeLock([['name' => 'kariricode/contract', 'version' => 'v2.12.0'], ['name' => 'kariricode/console', 'version' => 'v4.6.0']], []);
        self::assertSame(ExitCode::Success, $this->runInDir('check:ecosystem')->exitCode);
        $text = implode("\n", $this->output->lines);
        self::assertStringContainsString('<info>OK</info>', $text);
        self::assertStringContainsString('<comment>NOT INSTALLED</comment>', $text);
        self::assertStringContainsString('Every installed KaririCode package meets', $text);
    }

    public function testCheckEcosystemTableIsPinned(): void
    {
        $this->writeLock([['name' => 'kariricode/contract', 'version' => 'v2.12.1'], ['name' => 'kariricode/console', 'version' => 'v4.5.0'], ['name' => 'kariricode/cache', 'version' => '4.1.x-dev']], []);

        self::assertSame(ExitCode::Failure, $this->runInDir('check:ecosystem')->exitCode);
        $rule = '  ' . str_repeat('─', 70);
        self::assertSame([
            '',
            '  <comment>KaririCode Ecosystem — Spec V4.3.3 / ARFA 1.3 line</comment>',
            $rule,
            '  PACKAGE                                  INSTALLED    MINIMUM      STATUS',
            $rule,
            '  kariricode/contract                      2.12.1       2.12.0       <info>OK</info>',
            '  kariricode/exception                     —            2.0.0        <comment>NOT INSTALLED</comment>',
            '  kariricode/class-discovery               —            2.0.0        <comment>NOT INSTALLED</comment>',
            '  kariricode/property-inspector            —            3.0.0        <comment>NOT INSTALLED</comment>',
            '  kariricode/processor-pipeline            —            3.0.0        <comment>NOT INSTALLED</comment>',
            '  kariricode/cache                         4.1.x-dev    4.1.0        <comment>DEV</comment>',
            '  kariricode/console                       4.5.0        4.6.0        <error>OUTDATED</error>',
            '  kariricode/router                        —            4.1.0        <comment>NOT INSTALLED</comment>',
            '  kariricode/database                      —            4.4.0        <comment>NOT INSTALLED</comment>',
            '',
            '<error>✗ 1 package(s) below the minimum version this CLI expects.</error>',
        ], $this->output->lines);
    }

    public function testCheckEcosystemJsonIsPrettyAndUnescaped(): void
    {
        $this->writeLock([['name' => 'kariricode/contract', 'version' => 'v2.12.0']], []);

        self::assertSame(ExitCode::Success, $this->runInDir('check:ecosystem', ['json' => true])->exitCode, 'nothing below its minimum');
        self::assertCount(1, $this->output->lines);
        self::assertStringStartsWith("[\n    {\n        \"package\": \"kariricode/contract\",\n        \"installed\": \"2.12.0\",", $this->output->lines[0]);
        self::assertStringContainsString('"installed": "—"', $this->output->lines[0]);
    }

    public function testCheckEcosystemReportsAMissingOrBrokenLock(): void
    {
        self::assertSame(ExitCode::Failure, $this->runInDir('check:ecosystem')->exitCode);
        self::assertSame(['<error>composer.lock not found — run from the project root after composer install.</error>'], $this->output->lines);

        file_put_contents($this->dir . '/composer.lock', '{"packages": [');
        self::assertSame(ExitCode::Failure, $this->runInDir('check:ecosystem')->exitCode);
        self::assertStringContainsString('not valid JSON', implode("\n", $this->output->lines));

        file_put_contents($this->dir . '/composer.lock', '"text"');
        self::assertSame(ExitCode::Failure, $this->runInDir('check:ecosystem')->exitCode);
        self::assertSame(['<error>composer.lock has an unexpected structure.</error>'], $this->output->lines);

        $this->writeLock([['name' => 'kariricode/console', 'version' => '4.1.0']], []);
        self::assertSame(ExitCode::Failure, $this->runInDir('check:ecosystem')->exitCode);
        self::assertStringContainsString('1 package(s) below the minimum', implode("\n", $this->output->lines));
    }

    public function testMakeEntityDryRunPrintsWithoutWriting(): void
    {
        $result = $this->runCommand('make:entity', ['path' => $this->dir, 'fields' => 'email:email', 'dry-run' => true], ['name' => 'customer']);

        self::assertSame(ExitCode::Success, $result->exitCode);
        self::assertSame([], glob($this->dir . '/*') ?: []);
        self::assertStringContainsString('final class Customer', implode("\n", $this->output->lines));
    }

    public function testMakeEntityReportsAnInvalidName(): void
    {
        $result = $this->runCommand('make:entity', ['path' => $this->dir], ['name' => 'class']);

        self::assertSame(ExitCode::Failure, $result->exitCode);
        self::assertStringContainsString('[CLI_8900]', implode("\n", $this->output->lines));
    }

    public function testInspectCommandsWithoutAdaptersFail(): void
    {
        self::assertSame(ExitCode::Failure, $this->runCommand('inspect:ast', [], ['file' => __FILE__])->exitCode);
        self::assertStringContainsString('SyntaxTreeDumper', implode("\n", $this->output->lines));
        self::assertSame(ExitCode::Failure, $this->runCommand('inspect:diagnostics', [], ['path' => $this->dir])->exitCode);
        self::assertSame(ExitCode::Success, $this->runCommand('inspect:container', ['format' => 'plain'])->exitCode, 'no BindingSource: an empty report');
    }

    private RecordingOutput $output;

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $arguments
     */
    private function runCommand(string $command, array $options = [], array $arguments = []): \KaririCode\Console\ValueObject\CommandResult
    {
        $this->output = new RecordingOutput();

        return $this->application->run($command, new ArrayInput($arguments, $options), $this->output);
    }

    /**
     * @param list<mixed> $packages
     * @param list<mixed> $devPackages
     */
    private function writeLock(array $packages, array $devPackages): void
    {
        file_put_contents($this->dir . '/composer.lock', json_encode(['packages' => $packages, 'packages-dev' => $devPackages], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $options */
    private function runInDir(string $command, array $options = []): \KaririCode\Console\ValueObject\CommandResult
    {
        $cwd = (string) getcwd();
        chdir($this->dir);

        try {
            return $this->runCommand($command, $options);
        } finally {
            chdir($cwd);
        }
    }
}
