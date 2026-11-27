<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Enum;

use KaririCode\Cli\Enum\ViolationSeverity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViolationSeverity::class)]
final class ViolationSeverityTest extends TestCase
{
    public function testFromLabelIsCaseInsensitiveAndDefaultsToP1(): void
    {
        self::assertSame(ViolationSeverity::P0, ViolationSeverity::fromLabel(' p0 '));
        self::assertSame(ViolationSeverity::P1, ViolationSeverity::fromLabel('P1'));
        self::assertSame(ViolationSeverity::P1, ViolationSeverity::fromLabel('high'));
    }

    public function testP1ThresholdListsEverything(): void
    {
        self::assertTrue(ViolationSeverity::P1->includes(ViolationSeverity::P0));
        self::assertTrue(ViolationSeverity::P1->includes(ViolationSeverity::P1));
    }

    public function testP0ThresholdListsOnlyBuildBreakingViolations(): void
    {
        // Regression: check:arfa's default threshold hid every P0 row.
        self::assertTrue(ViolationSeverity::P0->includes(ViolationSeverity::P0));
        self::assertFalse(ViolationSeverity::P0->includes(ViolationSeverity::P1));
    }
}
