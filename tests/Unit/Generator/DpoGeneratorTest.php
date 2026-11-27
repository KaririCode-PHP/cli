<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Generator;

use KaririCode\Cli\Enum\FieldType;
use KaririCode\Cli\Enum\GeneratorTarget;
use KaririCode\Cli\Generator\DpoGenerator;
use KaririCode\Cli\ValueObject\FieldDescriptor;
use KaririCode\Cli\ValueObject\GeneratorContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DpoGenerator::class)]
final class DpoGeneratorTest extends TestCase
{
    private DpoGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new DpoGenerator();
    }

    public function testGetTypeReturnsDpo(): void
    {
        self::assertSame('DPO', $this->generator->type);
    }

    public function testGenerateReturnsOneFile(): void
    {
        $context = $this->makeContext();
        $files   = $this->generator->generate($context);

        self::assertCount(1, $files);
    }

    public function testGeneratedFileHasCorrectPath(): void
    {
        $context = $this->makeContext();
        $files   = $this->generator->generate($context);

        self::assertStringEndsWith('UserRegistrationDpo.php', $files[0]->path);
    }

    public function testGeneratedContentHasDeclareStrictTypes(): void
    {
        $context = $this->makeContext();
        $content = $this->generator->generate($context)[0]->content;

        self::assertStringContainsString('declare(strict_types=1)', $content);
    }

    public function testGeneratedContentHasFinalReadonlyClass(): void
    {
        $context = $this->makeContext();
        $content = $this->generator->generate($context)[0]->content;

        self::assertStringContainsString('final readonly class UserRegistrationDpo', $content);
    }

    public function testGeneratedContentHasMapInputAttribute(): void
    {
        $context = $this->makeContext();
        $content = $this->generator->generate($context)[0]->content;

        self::assertStringContainsString('#[MapInput]', $content);
    }

    public function testGeneratedContentContainsFieldTypes(): void
    {
        $context = $this->makeContextWithFields();
        $content = $this->generator->generate($context)[0]->content;

        self::assertStringContainsString('string $email', $content);
        self::assertStringContainsString('string $password', $content);
        self::assertStringContainsString('int $age', $content);
    }

    public function testGeneratedContentContainsValidateAttribute(): void
    {
        $context = $this->makeContextWithFields();
        $content = $this->generator->generate($context)[0]->content;

        self::assertStringContainsString('#[Validate(', $content);
        self::assertStringContainsString('#[Sanitize(', $content);
    }

    public function testGeneratedContentHasCorrectNamespace(): void
    {
        $context = $this->makeContext();
        $content = $this->generator->generate($context)[0]->content;

        self::assertStringContainsString('namespace App\\Domain\\Auth;', $content);
    }

    public function testEmptyFieldsProducesPlaceholderComment(): void
    {
        $context = $this->makeContext();
        $content = $this->generator->generate($context)[0]->content;

        self::assertStringContainsString('TODO: Add fields', $content);
    }

    public function testLineCountIsAccurate(): void
    {
        $context = $this->makeContextWithFields();
        $file    = $this->generator->generate($context)[0];

        $expected = substr_count($file->content, "\n") + 1;
        self::assertSame($expected, $file->lineCount());
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function makeContext(): GeneratorContext
    {
        return new GeneratorContext(
            className: 'UserRegistrationDpo',
            namespace: 'App\\Domain\\Auth',
            target: GeneratorTarget::Dpo,
            outputPath: '/tmp/cli-test',
        );
    }

    private function makeContextWithFields(): GeneratorContext
    {
        return new GeneratorContext(
            className: 'UserRegistrationDpo',
            namespace: 'App\\Domain\\Auth',
            target: GeneratorTarget::Dpo,
            outputPath: '/tmp/cli-test',
            fields: [
                new FieldDescriptor(
                    name: 'email',
                    type: FieldType::Email,
                    validators: ['NotBlank', 'Email'],
                    sanitizers: ['Trim', 'Lowercase'],
                ),
                new FieldDescriptor(
                    name: 'password',
                    type: FieldType::String,
                    validators: ['NotBlank', 'Length(min:8)'],
                    sanitizers: ['Trim'],
                ),
                new FieldDescriptor(
                    name: 'age',
                    type: FieldType::Int,
                ),
            ],
        );
    }
}
