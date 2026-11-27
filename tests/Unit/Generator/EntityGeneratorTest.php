<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Generator;

use KaririCode\Cli\Enum\FieldType;
use KaririCode\Cli\Enum\GeneratorTarget;
use KaririCode\Cli\Generator\EntityGenerator;
use KaririCode\Cli\ValueObject\FieldDescriptor;
use KaririCode\Cli\ValueObject\GeneratorContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityGenerator::class)]
final class EntityGeneratorTest extends TestCase
{
    private EntityGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new EntityGenerator();
    }

    public function testGetTypeReturnsEntity(): void
    {
        self::assertSame('Entity', $this->generator->type);
    }

    public function testIdentityIsPublicReadonly(): void
    {
        $content = $this->generate([]);

        self::assertStringContainsString('final class User', $content);
        self::assertStringContainsString("\n        public readonly string \$id,", $content);
        self::assertStringContainsString('public static function create(string $id): self', $content);
        self::assertStringContainsString('public function equals(self $other): bool', $content);
    }

    public function testStateUsesAsymmetricVisibility(): void
    {
        // Spec V4.3.3 type taxonomy: Entity = final class + public private(set).
        $content = $this->generate([
            new FieldDescriptor(name: 'name', type: FieldType::String),
            new FieldDescriptor(name: 'email', type: FieldType::Email),
        ]);

        self::assertStringContainsString("\n        public private(set) string \$name,", $content);
        self::assertStringContainsString("\n        public private(set) string \$email,", $content);
        self::assertStringContainsString('create(string $id, string $name, string $email): self', $content);
    }

    public function testNoJavaStyleGettersAreGenerated(): void
    {
        $content = $this->generate([
            new FieldDescriptor(name: 'name', type: FieldType::String),
        ]);

        self::assertStringNotContainsString('public function name()', $content);
        self::assertStringNotContainsString('public function id()', $content);
    }

    public function testSectionMarkersAreSeventyTwoColumns(): void
    {
        preg_match_all('/^\s*(\/\/ ──.*)$/mu', $this->generate([]), $matches);

        self::assertNotEmpty($matches[1]);
        foreach ($matches[1] as $marker) {
            self::assertSame(72, mb_strlen($marker), $marker);
        }
    }

    /** @param list<FieldDescriptor> $fields */
    private function generate(array $fields): string
    {
        $context = new GeneratorContext(
            className: 'User',
            namespace: 'App\\Domain\\Entity',
            target: GeneratorTarget::Entity,
            outputPath: '/tmp/kcli-test',
            fields: $fields,
        );

        return $this->generator->generate($context)[0]->content;
    }
}
