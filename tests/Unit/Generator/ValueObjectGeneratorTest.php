<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Generator;

use KaririCode\Cli\Enum\FieldType;
use KaririCode\Cli\Enum\GeneratorTarget;
use KaririCode\Cli\Generator\ValueObjectGenerator;
use KaririCode\Cli\ValueObject\FieldDescriptor;
use KaririCode\Cli\ValueObject\GeneratorContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValueObjectGenerator::class)]
final class ValueObjectGeneratorTest extends TestCase
{
    private ValueObjectGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ValueObjectGenerator();
    }

    public function testGetTypeReturnsValueObject(): void
    {
        self::assertSame('ValueObject', $this->generator->type);
    }

    public function testGeneratesFinalReadonlyClassWithFactory(): void
    {
        $content = $this->generate([]);

        self::assertStringContainsString('final readonly class Money', $content);
        self::assertStringContainsString('public static function of(', $content);
        self::assertStringContainsString('public function equals(self $other): bool', $content);
    }

    public function testPropertiesAreAlignedAtEightSpaces(): void
    {
        // Regression: promoted properties must sit at 8 spaces, not 12.
        $content = $this->generate([
            new FieldDescriptor(name: 'amount', type: FieldType::Float),
            new FieldDescriptor(name: 'currency', type: FieldType::String),
        ]);

        self::assertStringContainsString("\n        public float \$amount,", $content);
        self::assertStringContainsString("\n        public string \$currency,", $content);
        self::assertStringNotContainsString("\n            public float \$amount,", $content);
    }

    public function testNotBlankGuardIsEmittedAtBodyIndent(): void
    {
        // Regression: guard statements live in the factory body at 8 spaces.
        $content = $this->generate([
            new FieldDescriptor(name: 'slug', type: FieldType::String, validators: ['NotBlank']),
        ]);

        self::assertStringContainsString("\n        if (trim(\$slug) === '') {", $content);
        self::assertStringContainsString('InvalidArgumentException', $content);
        self::assertStringNotContainsString("\n            if (trim(\$slug)", $content);
    }

    public function testNoValidatorsProducesGuardPlaceholder(): void
    {
        $content = $this->generate([
            new FieldDescriptor(name: 'code', type: FieldType::String),
        ]);

        self::assertStringContainsString('// TODO: add domain invariant guards', $content);
    }

    /** @param list<FieldDescriptor> $fields */
    private function generate(array $fields): string
    {
        $context = new GeneratorContext(
            className: 'Money',
            namespace: 'App\\Domain\\ValueObject',
            target: GeneratorTarget::ValueObject,
            outputPath: '/tmp/cli-test',
            fields: $fields,
        );

        return $this->generator->generate($context)[0]->content;
    }

    public function testSectionMarkersAreSeventyTwoColumns(): void
    {
        preg_match_all('/^\s*(\/\/ ──.*)$/mu', $this->generate([]), $matches);

        self::assertNotEmpty($matches[1]);
        foreach ($matches[1] as $marker) {
            self::assertSame(72, mb_strlen($marker), $marker);
        }
    }
}
