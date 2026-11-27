<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Command;

use KaririCode\Cli\Command\Check\CheckArfaCommand;
use KaririCode\Cli\Command\Inspect\InspectAstCommand;
use KaririCode\Cli\Command\Inspect\InspectContainerCommand;
use KaririCode\Cli\Command\Inspect\InspectDiagnosticsCommand;
use KaririCode\Cli\Contract\BindingSource;
use KaririCode\Cli\Contract\DiagnosticsSource;
use KaririCode\Cli\Contract\SyntaxTreeDumper;
use KaririCode\Cli\Exception\ScaffoldFailureException;
use KaririCode\Cli\Inference\FieldInferenceEngine;
use KaririCode\Cli\Inspector\ContainerInspector;
use KaririCode\Cli\Template\OutputRenderer;
use KaririCode\Cli\Tests\Support\RecordingOutput;
use KaririCode\Cli\ValueObject\BindingEntry;
use KaririCode\Cli\ValueObject\InspectionResult;
use KaririCode\Console\Core\Application;
use KaririCode\Console\Enum\ExitCode;
use KaririCode\Console\Input\ArrayInput;
use KaririCode\Console\Provider\ConsoleServiceProvider;
use KaririCode\Console\ValueObject\CommandResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** The inspect commands with their adapters in place, and the input the inference engine accepts. */
#[CoversClass(InspectAstCommand::class)]
#[CoversClass(InspectDiagnosticsCommand::class)]
#[CoversClass(InspectContainerCommand::class)]
#[CoversClass(CheckArfaCommand::class)]
#[CoversClass(FieldInferenceEngine::class)]
final class AdapterCommandsTest extends TestCase
{
    private RecordingOutput $output;

    public function testInspectAstDumpsThroughTheAdapter(): void
    {
        $dumper = new class implements SyntaxTreeDumper {
            /** @var list<array{string, string, bool}> */
            public array $calls = [];

            public function dump(string $file, string $format, bool $withPositions): string
            {
                $this->calls[] = [basename($file), $format, $withPositions];

                return 'TREE';
            }
        };
        $application = self::application(new InspectAstCommand($dumper));

        self::assertSame(ExitCode::Success, $this->runOn($application, 'inspect:ast', ['format' => 'json', 'show-positions' => true], ['file' => __FILE__])->exitCode);
        self::assertSame(['TREE'], $this->output->lines);
        self::assertSame([[basename(__FILE__), 'json', true]], $dumper->calls);

        self::assertSame(ExitCode::Invalid, $this->runOn($application, 'inspect:ast', [], ['file' => __DIR__ . '/missing.php'])->exitCode);
        self::assertSame(['<error>✗ The file does not exist or is not readable.</error>'], $this->output->lines);
    }

    public function testInspectAstReportsAnAdapterFailure(): void
    {
        $dumper = new class implements SyntaxTreeDumper {
            public function dump(string $file, string $format, bool $withPositions): string
            {
                throw ScaffoldFailureException::writeFailed('x', 'adapter');
            }
        };

        self::assertSame(ExitCode::Failure, $this->runOn(self::application(new InspectAstCommand($dumper)), 'inspect:ast', [], ['file' => __FILE__])->exitCode);
        self::assertStringContainsString('[CLI_8941]', $this->output->lines[0]);
    }

    public function testInspectDiagnosticsValidatesAndFollowsTheErrorCount(): void
    {
        $source = new class implements DiagnosticsSource {
            public int $errors = 0;

            /** @var list<array{string, int}> */
            public array $calls = [];

            public function diagnose(string $path, string $minimumSeverity, int $maxFiles): InspectionResult
            {
                $this->calls[] = [$minimumSeverity, $maxFiles];

                return new InspectionResult('Diagnostics', [['file' => 'a.php', 'message' => 'm']], ['errors' => $this->errors]);
            }
        };
        $application = self::application(new InspectDiagnosticsCommand(new OutputRenderer(), $source));

        self::assertSame(ExitCode::Invalid, $this->runOn($application, 'inspect:diagnostics', ['severity' => 'fatal'], ['path' => __DIR__])->exitCode);
        self::assertSame(ExitCode::Success, $this->runOn($application, 'inspect:diagnostics', ['severity' => 'WARNING', 'max-files' => '3', 'format' => 'json'], ['path' => __DIR__])->exitCode);
        self::assertTrue(json_validate($this->output->lines[0]));
        $source->errors = 2;
        self::assertSame(ExitCode::Failure, $this->runOn($application, 'inspect:diagnostics', ['max-files' => '-4'], ['path' => __DIR__])->exitCode);
        self::assertSame([['warning', 3], ['info', 0]], $source->calls, 'severity lower-cased, max-files never negative');
    }

    public function testInspectContainerFiltersCaseInsensitively(): void
    {
        $source = new class implements BindingSource {
            public function bindings(): iterable
            {
                yield new BindingEntry('Psr\\Log\\LoggerInterface', 'App\\Log', true);
                yield new BindingEntry('App\\Clock', 'App\\SystemClock');
            }
        };
        $application = self::application(new InspectContainerCommand(new ContainerInspector($source), new OutputRenderer()));

        self::assertSame(ExitCode::Success, $this->runOn($application, 'inspect:container', ['filter' => 'LOGGER', 'format' => 'plain'])->exitCode);
        self::assertSame(['abstract=Psr\\Log\\LoggerInterface | concrete=App\\Log | singleton=yes'], $this->output->lines);
        $this->runOn($application, 'inspect:container', ['format' => 'plain']);
        self::assertSame(['abstract=App\\Clock | concrete=App\\SystemClock | singleton=no', 'abstract=Psr\\Log\\LoggerInterface | concrete=App\\Log | singleton=yes'], $this->output->lines, 'sorted by abstract');
    }

    public function testCheckArfaListsOnlyTheThresholdAndWarnsOnP1(): void
    {
        $dir = sys_get_temp_dir() . '/kcli-arfa-' . bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents($dir . '/A.php', "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Role.\n *\n * Preconditions: none.\n * Thread-safety: n/a.\n * Side effects: none.\n *\n * Example:\n *   x();\n *\n * References: none.\n */\nfinal class A\n{\n    private const LIMIT = 3;\n}\n");

        try {
            $application = self::application(new CheckArfaCommand(new OutputRenderer()));
            self::assertSame(ExitCode::Success, $this->runOn($application, 'check:arfa', ['path' => $dir, 'format' => 'plain'])->exitCode);
            self::assertCount(1, $this->output->lines);
            self::assertStringStartsWith('code=V11 | severity=P1', $this->output->lines[0]);

            $this->runOn($application, 'check:arfa', ['path' => $dir, 'format' => 'plain', 'severity' => 'P0']);
            self::assertSame([], $this->output->lines, 'P0 threshold hides P1 rows');

            $this->runOn($application, 'check:arfa', ['path' => $dir]);
            self::assertStringContainsString('<comment>⚠ 1 P1 violation(s) — review recommended</comment>', implode("\n", $this->output->lines));

            file_put_contents($dir . '/A.php', "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Role.\n *\n * Preconditions: none.\n * Thread-safety: n/a.\n * Side effects: none.\n *\n * Example:\n *   x();\n *\n * References: none.\n */\nfinal class A {}\n");
            self::assertSame(ExitCode::Success, $this->runOn($application, 'check:arfa', ['path' => $dir, 'fail-on-p1' => true])->exitCode, 'a clean tree passes --fail-on-p1');
        } finally {
            unlink($dir . '/A.php');
            rmdir($dir);
        }
    }

    public function testFieldTokensAreTrimmedAndNamesMatchRulesInAnyCase(): void
    {
        $engine = new FieldInferenceEngine();

        $email = $engine->parseAndEnrich(' Email : string ');
        self::assertSame('Email', $email->name);
        self::assertSame(['NotBlank', 'Email'], $email->validators, 'the name rule applies whatever the case');
        self::assertSame(['Trim', 'Lowercase'], $email->sanitizers);

        $byType = $engine->parseAndEnrich('contact:email');
        self::assertSame(['Email'], $byType->validators, 'the type rule applies when no name rule does');

        $named = $engine->parseAndEnrich('cpf:email');
        self::assertSame(['NotBlank', 'Cpf'], $named->validators, 'a name rule wins over the type rule');
        self::assertSame(['Trim', 'Digits'], $named->sanitizers);

        self::assertSame('String', $engine->parseAndEnrich('note:string:extra')->type->name, 'only the first colon separates the type');
    }

    private static function application(\KaririCode\Console\Contract\Command $command): Application
    {
        return ConsoleServiceProvider::buildApplication()->add($command);
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $arguments
     */
    private function runOn(Application $application, string $command, array $options = [], array $arguments = []): CommandResult
    {
        $this->output = new RecordingOutput();

        return $application->run($command, new ArrayInput($arguments, $options), $this->output);
    }
}
