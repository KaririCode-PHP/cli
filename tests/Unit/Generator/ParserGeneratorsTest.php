<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Generator;

use KaririCode\Cli\Enum\GeneratorTarget;
use KaririCode\Cli\Generator\GrammarGenerator;
use KaririCode\Cli\Generator\VisitorGenerator;
use KaririCode\Cli\ValueObject\GeneratorContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the Parser-extension generators.
 */
#[CoversClass(GrammarGenerator::class)]
#[CoversClass(VisitorGenerator::class)]
final class ParserGeneratorsTest extends TestCase
{
    public function testGrammarTypeAndStructure(): void
    {
        $g = new GrammarGenerator();
        self::assertSame('Grammar', $g->type);

        $content = $g->generate($this->context('BladeGrammar', GeneratorTarget::Grammar))[0]->content;
        self::assertStringStartsWith('<?php', $content);
        self::assertStringContainsString('final class BladeGrammar implements Grammar', $content);
    }

    public function testGrammarMethodBodiesAreIndented(): void
    {
        // Regression: a redundant dedent once pushed method bodies to column 0.
        $content = (new GrammarGenerator())
            ->generate($this->context('BladeGrammar', GeneratorTarget::Grammar))[0]->content;

        self::assertStringContainsString("\n        return 'BladeGrammar';", $content);
        // Body must NOT be flush against the left margin.
        self::assertStringNotContainsString("\nreturn 'BladeGrammar';", $content);
    }

    public function testVisitorTypeAndStructure(): void
    {
        $v = new VisitorGenerator();
        self::assertSame('Visitor', $v->type);

        $content = $v->generate($this->context('ImportCollector', GeneratorTarget::Visitor))[0]->content;
        self::assertStringStartsWith('<?php', $content);
        self::assertStringContainsString('final class ImportCollector implements NodeVisitor', $content);
    }

    public function testVisitorMethodBodiesAreIndented(): void
    {
        $content = (new VisitorGenerator())
            ->generate($this->context('ImportCollector', GeneratorTarget::Visitor))[0]->content;

        self::assertStringContainsString("\n        return true;", $content);
        self::assertStringNotContainsString("\nreturn true;", $content);
    }

    private function context(string $class, GeneratorTarget $target): GeneratorContext
    {
        return new GeneratorContext(
            className: $class,
            namespace: 'App\\Parser',
            target: $target,
            outputPath: '/tmp/cli-test',
        );
    }
}
