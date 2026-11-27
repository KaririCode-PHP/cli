<?php

declare(strict_types=1);

// Accepted Spec V4.3.3 §13 breaches, each with its reason (QD-V433-COMPLIANCE §4).
return [
    // DPOs that carry the parsed CLI input; every call site uses named arguments.
    'GeneratorContext::__construct' => ['parameters' => 7],
    'FieldDescriptor::__construct'  => ['parameters' => 7],
    // Exhaustive match over the enum's own cases: a lookup table, one
    // expression per arm, kept total by the compiler.
    'FieldType::fromString'                => ['cyclomatic' => 14],
    'GeneratorTarget::label'               => ['cyclomatic' => 14],
    'GeneratorTarget::defaultSubDirectory' => ['cyclomatic' => 14],
];
