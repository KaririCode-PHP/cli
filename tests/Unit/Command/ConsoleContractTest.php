<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Command;

use KaririCode\Cli\Application\InputValue;
use KaririCode\Cli\Enum\GeneratorTarget;
use KaririCode\Cli\Generator\CommandGenerator;
use KaririCode\Cli\Integration\CliServiceProvider;
use KaririCode\Cli\ValueObject\GeneratorContext;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\Enum\ExitCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The commands, and the commands make:command writes, against the Command
 * contract of kariricode/console 4.6: name and description as interface
 * properties, execute() returning ExitCode. 4.1.0 returned int and had no
 * properties, so loading any command was a fatal error.
 */
#[CoversClass(CliServiceProvider::class)]
#[CoversClass(CommandGenerator::class)]
#[CoversClass(InputValue::class)]
final class ConsoleContractTest extends TestCase
{
    public function testEveryCommandLoadsAndCarriesItsAttributeName(): void
    {
        $names = [];
        foreach (CliServiceProvider::commands() as $command) {
            $attribute = (new \ReflectionClass($command))->getAttributes(\KaririCode\Console\Attribute\AsCommand::class)[0]->newInstance();
            self::assertSame($attribute->name, $command->name, $command::class);
            self::assertSame($attribute->description, $command->description, $command::class);
            $names[] = $command->name;
        }

        self::assertCount(14, $names);
        self::assertContains('make:command', $names);
        self::assertContains('check:arfa', $names);
    }

    public function testTheApplicationListsTheCommands(): void
    {
        $application = CliServiceProvider::buildApplication();

        self::assertNotNull($application->buildDefinitionFor('make:entity'));
        self::assertNotNull($application->buildDefinitionFor('inspect:diagnostics'));
        self::assertNull($application->buildDefinitionFor('make:nothing'));
    }

    public function testMakeCommandWritesACommandForTheConsoleContract(): void
    {
        $class   = 'Generated' . bin2hex(random_bytes(4)) . 'Command';
        $context = new GeneratorContext(className: $class, namespace: 'KaririCode\\Cli\\Tests\\Generated', target: GeneratorTarget::Command, outputPath: sys_get_temp_dir());
        $file    = (new CommandGenerator())->generate($context)[0];
        $path    = sys_get_temp_dir() . "/{$class}.php";
        file_put_contents($path, $file->content);

        try {
            require $path;
            $fqcn    = "KaririCode\\Cli\\Tests\\Generated\\{$class}";
            $command = new $fqcn();
            self::assertInstanceOf(Command::class, $command);
            self::assertSame(strtolower(substr($class, 0, -strlen('Command'))), $command->name, 'hex digits are lower case, so no ":" is inserted');
            self::assertSame(ExitCode::Success, $command->execute($this->createStub(Input::class), $this->createStub(Output::class)));
        } finally {
            unlink($path);
        }
    }

    /** @return iterable<string, array{mixed, string, bool, int}> */
    public static function inputs(): iterable
    {
        yield 'text' => ['src', 'src', false, 7];
        yield 'number as text' => [42, '42', false, 42];
        yield 'numeric string' => ['-3', '-3', false, -3];
        yield 'flag true' => [true, 'fallback', true, 7];
        yield 'truthy string' => ['yes', 'yes', true, 7];
        yield 'falsy string' => ['off', 'off', false, 7];
        yield 'missing' => [null, 'fallback', false, 7];
        yield 'array' => [['a'], 'fallback', false, 7];
    }

    #[DataProvider('inputs')]
    public function testInputValuesFallBackInsteadOfCasting(mixed $raw, string $text, bool $flag, int $integer): void
    {
        $input = $this->createStub(Input::class);
        $input->method('getOption')->willReturn($raw);
        $input->method('getArgument')->willReturn($raw);

        self::assertSame($text, InputValue::text($input, 'x', 'fallback'));
        self::assertSame($text, InputValue::argument($input, 'x', 'fallback'));
        self::assertSame($flag, InputValue::flag($input, 'x'));
        self::assertSame($integer, InputValue::integer($input, 'x', 7));
    }
}
