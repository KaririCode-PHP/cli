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

/**
 * The whole file make:dpo writes: imports only for the attributes in use,
 * sorted; one #[Validate] and #[Sanitize] per field that has rules; a
 * default turns a required field optional; snake_case names become
 * camelCase.
 */
#[CoversClass(DpoGenerator::class)]
final class DpoGeneratorGoldenTest extends TestCase
{
    public function testAFileWithoutFieldsHasAPlaceholder(): void
    {
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Dpo;

            use KaririCode\Console\Attribute\MapInput;

            /**
             * Data Preparation Object for SignUpDpo.
             *
             * Immutable command carrier validated and sanitized via the
             * KaririCode\ProcessorPipeline before reaching domain services.
             * All fields are set once in the constructor and sealed by PHP 8.4.
             *
             * ARFA 1.3: P1 (immutable state). Spec V4.3.3: DPO, final readonly.
             *
             * Preconditions: values passed the #[Validate] rules of each field.
             * Thread-safety: immutable; safe to share between fibers.
             * Side effects: none.
             *
             * Example:
             *   $dpo = new SignUpDpo(...);
             *
             * References:
             * @see https://www.php.net/manual/en/language.oop5.properties.php#language.oop5.properties.readonly-properties
             */
            #[MapInput]
            final readonly class SignUpDpo
            {
                public function __construct(
                    // TODO: Add fields
                ) {}
            }
            PHP;

        self::assertSame($expected, self::content([]));
    }

    public function testFieldsWithRulesDefaultsAndSnakeCaseNames(): void
    {
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Dpo;

            use KaririCode\Console\Attribute\MapInput;
            use KaririCode\Sanitizer\Attribute\Sanitize;
            use KaririCode\Validator\Attribute\Validate;

            /**
             * Data Preparation Object for SignUpDpo.
             *
             * Immutable command carrier validated and sanitized via the
             * KaririCode\ProcessorPipeline before reaching domain services.
             * All fields are set once in the constructor and sealed by PHP 8.4.
             *
             * ARFA 1.3: P1 (immutable state). Spec V4.3.3: DPO, final readonly.
             *
             * Preconditions: values passed the #[Validate] rules of each field.
             * Thread-safety: immutable; safe to share between fibers.
             * Side effects: none.
             *
             * Example:
             *   $dpo = new SignUpDpo(...);
             *
             * References:
             * @see https://www.php.net/manual/en/language.oop5.properties.php#language.oop5.properties.readonly-properties
             */
            #[MapInput]
            final readonly class SignUpDpo
            {
                public function __construct(
                    #[Validate(['Required', 'Email'])]
                    #[Sanitize(['Trim'])]
                    public string $email,

                    public string $nickName = 'anon',

                    public int $age = 18,
                ) {}
            }
            PHP;

        self::assertSame($expected, self::content([
            new FieldDescriptor(name: 'email', type: FieldType::Email, required: true, validators: ['Required', 'Email'], sanitizers: ['Trim']),
            new FieldDescriptor(name: 'nick_name', type: FieldType::String, required: true, default: 'anon'),
            new FieldDescriptor(name: 'age', type: FieldType::Int, required: false, default: 18),
        ]));
    }

    public function testAnyRuleImportsBothAttributesButWritesOnlyItsOwn(): void
    {
        $content = self::content([new FieldDescriptor(name: 'code', type: FieldType::String, sanitizers: ['Trim'])]);

        self::assertStringContainsString("use KaririCode\\Console\\Attribute\\MapInput;\nuse KaririCode\\Sanitizer\\Attribute\\Sanitize;\nuse KaririCode\\Validator\\Attribute\\Validate;\n", $content);
        self::assertStringNotContainsString('#[Validate([', $content);
        self::assertStringContainsString("        #[Sanitize(['Trim'])]\n" . '        public string $code,', $content);
    }

    /** @param list<FieldDescriptor> $fields */
    private static function content(array $fields): string
    {
        $context = new GeneratorContext(className: 'SignUpDpo', namespace: 'App\\Dpo', target: GeneratorTarget::Dpo, outputPath: '/tmp/out', fields: $fields);

        return (new DpoGenerator())->generate($context)[0]->content;
    }
}
