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

        self::assertStringContainsString("\n        return 'blade';", $content);
        // Body must NOT be flush against the left margin.
        self::assertStringNotContainsString("\nreturn 'blade';", $content);
    }

    public function testGrammarImplementsTheParserContract(): void
    {
        // kariricode/parser 4.1: Grammar declares id() and phpVersionRange().
        $content = (new GrammarGenerator())
            ->generate($this->context('TwigTemplateGrammar', GeneratorTarget::Grammar))[0]->content;

        self::assertStringContainsString("use KaririCode\\Parser\\Contract\\Grammar;\n", $content);
        self::assertStringContainsString("    public function id(): string\n    {\n        return 'twig-template';", $content);
        self::assertStringContainsString('    public function phpVersionRange(): string', $content);
        self::assertSame(2, substr_count($content, '#[\\Override]'));

        foreach (['Grammar' => 'grammar', 'Markdown' => 'markdown', 'SQL2Grammar' => 'sql2', 'X' => 'x'] as $class => $id) {
            $generated = (new GrammarGenerator())->generate($this->context($class, GeneratorTarget::Grammar))[0]->content;
            self::assertStringContainsString("return '{$id}';", $generated, $class);
        }
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

        self::assertStringContainsString("\n        return null;", $content);
        self::assertStringNotContainsString("\nreturn null;", $content);
    }

    public function testVisitorImplementsTheParserContract(): void
    {
        // kariricode/parser 4.1: a returned Node replaces the visited one, null keeps it.
        $content = (new VisitorGenerator())
            ->generate($this->context('ImportCollector', GeneratorTarget::Visitor))[0]->content;

        self::assertStringContainsString("use KaririCode\\Parser\\AST\\Node;\nuse KaririCode\\Parser\\Contract\\NodeVisitor;\n", $content);
        self::assertStringContainsString('    public function enterNode(Node $node): ?Node', $content);
        self::assertStringContainsString('    public function leaveNode(Node $node): ?Node', $content);
        self::assertStringContainsString('    public private(set) array $results = [];', $content);
        self::assertSame(2, substr_count($content, '#[\\Override]'));
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
