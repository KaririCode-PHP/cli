<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Conformance;

use KaririCode\Cli\Inspector\ArfaComplianceInspector;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Dogfooding: the component that enforces ARFA 1.3 / Spec V4.3.3 must pass
 * its own check. Uses whichever engine is available — AST when
 * kariricode/parser is installed, the regex fallback otherwise.
 */
#[CoversNothing]
final class SelfComplianceTest extends TestCase
{
    public function testOwnSourceTreeHasNoViolations(): void
    {
        $result = (new ArfaComplianceInspector(dirname(__DIR__, 2) . '/src'))->inspect();

        self::assertArrayNotHasKey('error', $result->summary);
        self::assertSame([], $result->rows, print_r($result->rows, true));
    }
}
