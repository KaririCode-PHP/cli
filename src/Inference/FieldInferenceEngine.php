<?php

declare(strict_types=1);

namespace KaririCode\Cli\Inference;

use KaririCode\Cli\Exception\InvalidScaffoldRequestException;
use KaririCode\Cli\Enum\FieldType;
use KaririCode\Cli\ValueObject\FieldDescriptor;

/**
 * Infers validators, sanitizers, and types from field names and declared types.
 *
 * Operates in two passes:
 *   1. Type resolution — maps the declared string type (e.g. 'email') to a
 *      FieldType enum, resolving aliases and domain types.
 *   2. Processor injection — maps the resolved type + field name pattern to a
 *      list of KaririCode\Validator and KaririCode\Sanitizer class names.
 *
 * The engine never mutates its input. It returns a new FieldDescriptor with
 * the enriched validators/sanitizers list (ARFA 1.3 P1).
 *
 * Why this beats Laravel Artisan:
 *   Artisan generates bare fields with no validation hints. Developers must
 *   manually add FormRequest rules. InferenceEngine auto-populates attributes
 *   so the generated DPO is immediately valid and safe for production.
 *
 * ARFA 1.3: P3 (adaptive context). Spec V4.3.3: stateless service.
 *
 * Preconditions: tokens follow name[:type]; anything else raises 8602.
 * Thread-safety: stateless; safe to share.
 * Side effects: none.
 *
 * Example:
 *   $fields = $engine->parseAll('email,password:string');
 *
 * References: ADR-002.
 * @see https://www.php.net/manual/en/language.attributes.php
 */
final class FieldInferenceEngine
{
    // ── Name-pattern → (validators, sanitizers) ──────────────────────────

    /** @var array<string, array{validators: list<string>, sanitizers: list<string>}> */
    private const array NAME_RULES = [
        'email'        => ['validators' => ['NotBlank', 'Email'], 'sanitizers' => ['Trim', 'Lowercase']],
        'password'     => ['validators' => ['NotBlank', 'Length(min:8)'], 'sanitizers' => ['Trim']],
        'name'         => ['validators' => ['NotBlank', 'Length(max:255)'], 'sanitizers' => ['Trim', 'Capitalize']],
        'first_name'   => ['validators' => ['NotBlank', 'Length(max:100)'], 'sanitizers' => ['Trim', 'Capitalize']],
        'last_name'    => ['validators' => ['NotBlank', 'Length(max:100)'], 'sanitizers' => ['Trim', 'Capitalize']],
        'username'     => ['validators' => ['NotBlank', 'AlphaNumeric', 'Length(min:3,max:30)'], 'sanitizers' => ['Trim', 'Lowercase']],
        'phone'        => ['validators' => ['NotBlank', 'Phone'], 'sanitizers' => ['Trim', 'Digits']],
        'cpf'          => ['validators' => ['NotBlank', 'Cpf'], 'sanitizers' => ['Trim', 'Digits']],
        'cnpj'         => ['validators' => ['NotBlank', 'Cnpj'], 'sanitizers' => ['Trim', 'Digits']],
        'url'          => ['validators' => ['NotBlank', 'Url'], 'sanitizers' => ['Trim']],
        'website'      => ['validators' => ['Url'], 'sanitizers' => ['Trim']],
        'slug'         => ['validators' => ['NotBlank', 'Slug'], 'sanitizers' => ['Trim', 'Lowercase', 'Slugify']],
        'title'        => ['validators' => ['NotBlank', 'Length(max:255)'], 'sanitizers' => ['Trim', 'Capitalize']],
        'description'  => ['validators' => ['Length(max:2000)'], 'sanitizers' => ['Trim', 'StripTags']],
        'content'      => ['validators' => ['NotBlank'], 'sanitizers' => ['Trim', 'StripTags']],
        'zip_code'     => ['validators' => ['NotBlank', 'ZipCode'], 'sanitizers' => ['Trim', 'Digits']],
        'postal_code'  => ['validators' => ['NotBlank', 'ZipCode'], 'sanitizers' => ['Trim', 'Digits']],
        'price'        => ['validators' => ['NotBlank', 'Positive'], 'sanitizers' => []],
        'amount'       => ['validators' => ['NotBlank', 'Positive'], 'sanitizers' => []],
        'quantity'     => ['validators' => ['NotBlank', 'Positive'], 'sanitizers' => []],
        'age'          => ['validators' => ['NotBlank', 'Range(min:0,max:150)'], 'sanitizers' => []],
        'status'       => ['validators' => ['NotBlank'], 'sanitizers' => ['Trim', 'Lowercase']],
        'token'        => ['validators' => ['NotBlank'], 'sanitizers' => ['Trim']],
        'uuid'         => ['validators' => ['NotBlank', 'Uuid'], 'sanitizers' => ['Trim', 'Lowercase']],
        'ip_address'   => ['validators' => ['Ip'], 'sanitizers' => ['Trim']],
        'locale'       => ['validators' => ['Locale'], 'sanitizers' => ['Trim', 'Lowercase']],
        'timezone'     => ['validators' => ['Timezone'], 'sanitizers' => ['Trim']],
        'currency'     => ['validators' => ['Currency'], 'sanitizers' => ['Trim', 'Uppercase']],
        'color'        => ['validators' => ['HexColor'], 'sanitizers' => ['Trim', 'Lowercase']],
        'birth_date'   => ['validators' => ['NotBlank', 'Date'], 'sanitizers' => []],
        'created_at'   => ['validators' => [], 'sanitizers' => []],
        'updated_at'   => ['validators' => [], 'sanitizers' => []],
    ];

    // ── Type → (validators, sanitizers) ──────────────────────────────────

    /** @var array<string, array{validators: list<string>, sanitizers: list<string>}> */
    private const array TYPE_RULES = [
        'email'  => ['validators' => ['Email'], 'sanitizers' => ['Lowercase']],
        'url'    => ['validators' => ['Url'], 'sanitizers' => ['Trim']],
        'phone'  => ['validators' => ['Phone'], 'sanitizers' => ['Digits']],
        'cpf'    => ['validators' => ['Cpf'], 'sanitizers' => ['Digits']],
        'cnpj'   => ['validators' => ['Cnpj'], 'sanitizers' => ['Digits']],
        'money'  => ['validators' => ['Positive'], 'sanitizers' => []],
        'uuid'   => ['validators' => ['Uuid'], 'sanitizers' => ['Lowercase']],
    ];

    // ── Public API ───────────────────────────────────────────────────────

    /**
     * Enriches a FieldDescriptor with inferred validators and sanitizers.
     *
     * Name-based rules take precedence over type-based rules. Explicit
     * validators/sanitizers on the input descriptor are merged and
     * deduplicated (input always wins when there is a conflict).
     */
    public function enrich(FieldDescriptor $field): FieldDescriptor
    {
        $nameKey  = strtolower($field->name);
        $typeKey  = strtolower($field->type->name);

        $nameRule = self::NAME_RULES[$nameKey] ?? null;
        $typeRule = self::TYPE_RULES[$typeKey] ?? null;

        $validators = $this->merge(
            $field->validators,
            $nameRule['validators'] ?? $typeRule['validators'] ?? [],
        );

        $sanitizers = $this->merge(
            $field->sanitizers,
            $nameRule['sanitizers'] ?? $typeRule['sanitizers'] ?? [],
        );

        return new FieldDescriptor(
            name: $field->name,
            type: $field->type,
            required: $field->required,
            default: $field->default,
            validators: $validators,
            sanitizers: $sanitizers,
            docBlock: $field->docBlock,
        );
    }

    /**
     * Parses a "name:type" token and returns an enriched FieldDescriptor.
     *
     * @throws InvalidScaffoldRequestException on malformed input
     */
    public function parseAndEnrich(string $fieldToken): FieldDescriptor
    {
        $parts = explode(':', $fieldToken, 2);
        $name  = trim($parts[0]);
        $type  = trim($parts[1] ?? 'string');

        if ($name === '' || !preg_match('/^[a-z][a-z0-9_]*\z/i', $name)) {
            throw InvalidScaffoldRequestException::invalidFieldDefinition();
        }

        $descriptor = FieldDescriptor::fromParsedString($name, $type);

        return $this->enrich($descriptor);
    }

    /**
     * Parses a comma-separated list of "name:type" tokens.
     *
     * @return list<FieldDescriptor>
     */
    public function parseAll(string $fieldsString): array
    {
        if (trim($fieldsString) === '') {
            return [];
        }

        return array_values(array_map(
            fn(string $token) => $this->parseAndEnrich(trim($token)),
            explode(',', $fieldsString),
        ));
    }

    // ── Private ──────────────────────────────────────────────────────────

    /**
     * Merges explicit entries with inferred defaults, preserving explicit first.
     *
     * @param list<string> $explicit
     * @param list<string> $inferred
     * @return list<string>
     */
    private function merge(array $explicit, array $inferred): array
    {
        return array_values(array_unique(array_merge($explicit, $inferred)));
    }
}
