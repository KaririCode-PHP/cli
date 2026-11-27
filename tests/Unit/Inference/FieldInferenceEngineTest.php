<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Inference;

use KaririCode\Cli\Enum\FieldType;
use KaririCode\Contract\Exception\ExceptionInterface;
use KaririCode\Cli\Inference\FieldInferenceEngine;
use KaririCode\Cli\ValueObject\FieldDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FieldInferenceEngine::class)]
final class FieldInferenceEngineTest extends TestCase
{
    private FieldInferenceEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new FieldInferenceEngine();
    }

    // ── parseAndEnrich ───────────────────────────────────────────────────

    public function testEmailFieldInfersEmailTypeAndValidators(): void
    {
        $field = $this->engine->parseAndEnrich('email:string');

        self::assertSame('email', $field->name);
        self::assertSame(FieldType::String, $field->type);
        self::assertContains('Email', $field->validators);
        self::assertContains('NotBlank', $field->validators);
        self::assertContains('Trim', $field->sanitizers);
        self::assertContains('Lowercase', $field->sanitizers);
    }

    public function testPasswordFieldInfersLengthValidator(): void
    {
        $field = $this->engine->parseAndEnrich('password:string');

        self::assertContains('Length(min:8)', $field->validators);
        self::assertContains('Trim', $field->sanitizers);
    }

    public function testCpfFieldInfersCpfValidatorAndDigitsSanitizer(): void
    {
        $field = $this->engine->parseAndEnrich('cpf:cpf');

        self::assertContains('Cpf', $field->validators);
        self::assertContains('Digits', $field->sanitizers);
    }

    public function testTypeWithoutNameRuleFallsBackToTypeRule(): void
    {
        // Field name 'document' has no name rule, but type 'email' has a type rule
        $descriptor = new FieldDescriptor(name: 'document', type: FieldType::Email);
        $enriched   = $this->engine->enrich($descriptor);

        self::assertContains('Email', $enriched->validators);
        self::assertContains('Lowercase', $enriched->sanitizers);
    }

    public function testPlainStringTypeProducesNoInferredProcessors(): void
    {
        $field = $this->engine->parseAndEnrich('notes:string');

        self::assertSame([], $field->validators);
        self::assertSame([], $field->sanitizers);
    }

    public function testExplicitValidatorsArePreservedAndMerged(): void
    {
        $base = new FieldDescriptor(
            name: 'email',
            type: FieldType::String,
            validators: ['MyCustomValidator'],
        );

        $enriched = $this->engine->enrich($base);

        self::assertContains('MyCustomValidator', $enriched->validators);
        self::assertContains('Email', $enriched->validators);
        // No duplicates
        self::assertSame(count($enriched->validators), count(array_unique($enriched->validators)));
    }

    // ── parseAll ─────────────────────────────────────────────────────────

    public function testParseAllReturnsMultipleDescriptors(): void
    {
        $fields = $this->engine->parseAll('email:string,name:string,age:int');

        self::assertCount(3, $fields);
        self::assertSame('email', $fields[0]->name);
        self::assertSame('name', $fields[1]->name);
        self::assertSame('age', $fields[2]->name);
        self::assertSame(FieldType::Int, $fields[2]->type);
    }

    public function testParseAllWithEmptyStringReturnsEmptyArray(): void
    {
        self::assertSame([], $this->engine->parseAll(''));
        self::assertSame([], $this->engine->parseAll('   '));
    }

    public function testInvalidFieldNameThrowsContractException(): void
    {
        $this->expectException(ExceptionInterface::class);
        $this->engine->parseAndEnrich('123invalid:string');
    }

    public function testTypeDefaultsToStringWhenOmitted(): void
    {
        $field = $this->engine->parseAndEnrich('username');

        self::assertSame(FieldType::String, $field->type);
    }

    // ── Type resolution ──────────────────────────────────────────────────

    public function testDatetimeTypeResolved(): void
    {
        $field = $this->engine->parseAndEnrich('birth_date:datetime');
        self::assertSame(FieldType::DateTimeImmutable, $field->type);
        self::assertSame('\DateTimeImmutable', $field->phpType());
    }

    public function testMoneyTypePhpTypeIsFloat(): void
    {
        $field = $this->engine->parseAndEnrich('price:money');
        self::assertSame(FieldType::Money, $field->type);
        self::assertSame('float', $field->phpType());
    }
}
