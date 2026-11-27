<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Application;

use KaririCode\Cli\Application\ScaffoldRunner;
use KaririCode\Cli\Enum\GeneratorTarget;
use KaririCode\Cli\Enum\WriteStatus;
use KaririCode\Cli\Generator\GeneratorRegistry;
use KaririCode\Cli\ValueObject\GeneratorContext;
use KaririCode\Console\Contract\Output;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScaffoldRunner::class)]
final class ScaffoldRunnerTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function classNames(): iterable
    {
        yield 'appends suffix' => ['UserRegistration', 'Dpo', 'UserRegistrationDpo'];
        yield 'keeps suffix' => ['UserRegistrationDpo', 'Dpo', 'UserRegistrationDpo'];
        yield 'suffix case-insensitive' => ['UserDPO', 'Dpo', 'UserDPO'];
        yield 'upper-cases first char' => ['blade', 'Grammar', 'BladeGrammar'];
        yield 'trims, no suffix' => ['  Money ', '', 'Money'];
    }

    #[DataProvider('classNames')]
    public function testClassNameNormalisation(string $raw, string $suffix, string $expected): void
    {
        self::assertSame($expected, ScaffoldRunner::className($raw, $suffix));
    }

    public function testAbsolutePathsAreNotJoinedToCwd(): void
    {
        // Regression: "--path=/tmp/out" became "<cwd>/tmp/out".
        self::assertSame('/tmp/out', ScaffoldRunner::outputPath('/tmp/out/'));
        self::assertSame('C:/out', ScaffoldRunner::outputPath('C:/out'));
    }

    public function testRelativePathsResolveAgainstCwd(): void
    {
        self::assertSame(rtrim((string) getcwd(), '/') . '/src/Domain', ScaffoldRunner::outputPath('src/Domain'));
    }

    public function testDryRunPrintsSourceWithoutWriting(): void
    {
        $lines  = [];
        $output = $this->createMock(Output::class);
        $output->method('writeln')->willReturnCallback(
            static function (mixed $message = '') use (&$lines): void {
                $lines[] = is_string($message) ? $message : '';
            },
        );

        $context = new GeneratorContext(
            className: 'Money',
            namespace: 'App\\ValueObject',
            target: GeneratorTarget::ValueObject,
            outputPath: '/tmp/kcli-never-written',
            dryRun: true,
        );

        $outcomes = (new ScaffoldRunner(new GeneratorRegistry()))->run($context, $output);

        self::assertCount(1, $outcomes);
        self::assertSame(WriteStatus::DryRun, $outcomes[0]->status);
        self::assertStringContainsString('DRY-RUN', implode("\n", $lines));
        self::assertStringContainsString('final readonly class Money', implode("\n", $lines));
        self::assertDirectoryDoesNotExist('/tmp/kcli-never-written');
    }
}
