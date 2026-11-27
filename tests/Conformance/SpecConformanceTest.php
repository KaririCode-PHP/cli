<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Conformance;

use KaririCode\Contract\Exception\Configuration;
use KaririCode\Contract\Exception\ContainerFailure;
use KaririCode\Contract\Exception\NotFound;
use KaririCode\Contract\Exception\Protocol;
use KaririCode\Contract\Exception\Runtime;
use KaririCode\Contract\Exception\Validation;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Spec V4.3.3 rules that are cheaper to state as tests than as review notes.
 * Each test names the rule; QD-V433-COMPLIANCE §3 maps rules to tests.
 */
#[CoversNothing]
final class SpecConformanceTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';

    /** @var list<string> catch types reviewed for src/ (fail-closed rule) */
    private const array REVIEWED_CATCHES = ['ExceptionInterface', '\JsonException'];

    /** @var list<class-string> */
    private const array FAMILIES = [Validation::class, NotFound::class, Configuration::class, Protocol::class, ContainerFailure::class, Runtime::class];

    public function testEverySourceFileIsStrictAndDeclaresExactlyOneType(): void
    {
        foreach ($this->sources() as $file => $code) {
            self::assertStringContainsString('declare(strict_types=1);', $code, $file);
            self::assertSame(1, preg_match_all('/^(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+\w+/m', $code), $file);
        }
    }

    public function testDocblocksCarryTheFiveElementsAndNoBoilerplateTags(): void
    {
        foreach ($this->sources() as $file => $code) {
            self::assertSame(0, preg_match('/^\s*\*\s*@(?:package|author|license|since|copyright)\b/m', $code), $file);

            // The type's own docblock: a generator's template also carries
            // these elements, and a search of the whole file finds those.
            $doc = (string) self::reflect($code)->getDocComment();

            foreach (['Preconditions:', 'Thread-safety:', 'Side effects:', 'Example:', 'References:', '@see https://'] as $element) {
                self::assertStringContainsString($element, $doc, "{$file}: the type's docblock lacks {$element}");
            }
        }
    }

    public function testSectionMarkersAreSeventyTwoColumns(): void
    {
        foreach ($this->sources() as $file => $code) {
            preg_match_all('/^\s*(\/\/ ──.*)$/mu', $code, $matches);

            foreach ($matches[1] as $marker) {
                self::assertSame(72, mb_strlen(rtrim($marker)), $file);
            }
        }
    }

    public function testConcreteClassesAreFinalAndConstantsTyped(): void
    {
        foreach ($this->sources() as $file => $code) {
            self::assertSame(0, preg_match('/^(?!final\b)(?:readonly\s+)?class\s+\w+/m', $code), "{$file}: concrete class not final");
            self::assertSame(0, preg_match('/^\s*(?:(?:public|protected|private)\s+)?const\s+[A-Z][A-Z0-9_]*\s*=/m', $code), "{$file}: untyped constant");
        }
    }

    public function testRegexesAnchorWithBackslashZAndNoGettersOrObjectParameters(): void
    {
        foreach ($this->sources() as $file => $code) {
            self::assertSame(0, preg_match("/preg_[a-z_]+\\(\\s*'[^']*\\\$\\/(?![a-zA-Z]*m)[a-zA-Z]*'/", $code), "{$file}: \$ end anchor");
            self::assertSame(0, preg_match('/public\s+function\s+get[A-Z]\w*\s*\(\s*\)/', $code), "{$file}: getter");
            self::assertSame(0, preg_match('/function\s+\w+\s*\([^)]*\??\bobject\s+\$/', $code), "{$file}: object parameter (§31)");
        }
    }

    public function testEachExceptionImplementsExactlyOneContractFamily(): void
    {
        foreach (glob(self::ROOT . '/src/Exception/*.php') ?: [] as $path) {
            $class = 'KaririCode\\Cli\\Exception\\' . basename($path, '.php');
            self::assertTrue(class_exists($class), $class);
            $reflection = new \ReflectionClass($class);
            $families   = array_filter(self::FAMILIES, static fn(string $family): bool => $reflection->implementsInterface($family));

            self::assertCount(1, $families, $class);
            self::assertTrue($reflection->isFinal(), $class);
            self::assertTrue($reflection->getConstructor()?->isPrivate() ?? false, $class);
        }
    }

    public function testErrorCodesAreUniqueAndInsideTheCliBlock(): void
    {
        $codes = [];

        foreach (glob(self::ROOT . '/src/Exception/*.php') ?: [] as $path) {
            preg_match_all('/\[CLI_(\d{4})\][^\n]*?,\s*(\d{4})\)/', (string) file_get_contents($path), $matches, PREG_SET_ORDER);

            foreach ($matches as [, $prefix, $code]) {
                self::assertSame($prefix, $code, 'message prefix and code differ');
                self::assertGreaterThanOrEqual(8900, (int) $code);
                self::assertLessThanOrEqual(8999, (int) $code);
                $codes[] = $code;
            }
        }

        self::assertNotEmpty($codes);
        self::assertSame($codes, array_values(array_unique($codes)));
    }

    #[Group('security')]
    public function testValidationMessagesNeverEchoTheRejectedValue(): void
    {
        $reflection = new \ReflectionClass(\KaririCode\Cli\Exception\InvalidScaffoldRequestException::class);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_STATIC) as $factory) {
            self::assertSame(0, $factory->getNumberOfParameters(), "{$factory->getName()} must not receive the rejected value (E-047)");
        }
    }

    #[Group('security')]
    public function testEveryCatchInSourceIsReviewed(): void
    {
        foreach ($this->sources() as $file => $code) {
            preg_match_all('/catch\s*\(\s*([\\\\\w]+)/', $code, $matches);

            foreach ($matches[1] as $type) {
                self::assertContains($type, self::REVIEWED_CATCHES, "{$file} catches {$type}");
            }
        }
    }

    public function testComposerMetadataFollowsGateG4(): void
    {
        /** @var array{extra: array{kariricode?: array<string, mixed>, arfa?: mixed}, require: array<string, string>} $composer the manifest of this repository */
        $composer   = json_decode((string) file_get_contents(self::ROOT . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $kariricode = $composer['extra']['kariricode'] ?? [];

        self::assertSame(['version' => '1.3', 'level' => 1], $kariricode['arfa'] ?? null);
        self::assertSame(['version' => '5.0', 'level' => 1], $kariricode['asvs'] ?? null);
        self::assertSame('4.3.3', $kariricode['spec'] ?? null);
        self::assertArrayNotHasKey('arfa', $composer['extra'], 'the V4.0 extra.arfa block is superseded');
        self::assertSame('>=8.4', $composer['require']['php'] ?? null);
        self::assertArrayHasKey('kariricode/contract', $composer['require']);
    }

    public function testSupplyChainFollowsSection32(): void
    {
        self::assertFileExists(self::ROOT . '/tools/sbom.php');
        self::assertStringNotContainsString('composer.lock', (string) file_get_contents(self::ROOT . '/.gitignore'));

        preg_match_all('/uses:\s*(\S+)/', (string) file_get_contents(self::ROOT . '/.github/workflows/ci.yml'), $matches);

        self::assertNotEmpty($matches[1]);
        foreach ($matches[1] as $action) {
            self::assertMatchesRegularExpression('/@[0-9a-f]{40}\z/', $action, "{$action} is not pinned by commit SHA");
        }
    }

    public function testDocumentationFollowsTheCanonicalLayout(): void
    {
        foreach (['docs/spec/SPEC-001-cli.md', 'docs/quality/QD-V433-COMPLIANCE.md', 'docs/security/SECURITY.md', 'CHANGELOG.md', 'README.md'] as $document) {
            self::assertFileExists(self::ROOT . '/' . $document);
        }

        $adrs = glob(self::ROOT . '/docs/adr/ADR-[0-9][0-9][0-9]-*.md') ?: [];
        self::assertGreaterThanOrEqual(12, count($adrs));

        foreach ($adrs as $adr) {
            preg_match_all('/^## (.+)$/m', (string) file_get_contents($adr), $sections);
            $order = array_values(array_intersect($sections[1], ['Context', 'Decision', 'Consequences', 'Alternatives Considered', 'References']));

            self::assertSame(['Context', 'Decision', 'Consequences', 'Alternatives Considered', 'References'], $order, basename($adr));
        }
    }

    /** @return \ReflectionClass<object> */
    private static function reflect(string $code): \ReflectionClass
    {
        preg_match('/^namespace\s+([^;]+);/m', $code, $namespace);
        preg_match('/^(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $code, $type);
        $name = ($namespace[1] ?? '') . '\\' . ($type[1] ?? '');

        if (!class_exists($name) && !interface_exists($name) && !trait_exists($name)) {
            self::fail("{$name} does not autoload");
        }

        return new \ReflectionClass($name);
    }

    /** @return array<string, string> */
    private function sources(): array
    {
        $files    = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/src', \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $entry) {
            if ($entry instanceof \SplFileInfo && $entry->getExtension() === 'php') {
                $files[substr($entry->getPathname(), strlen(self::ROOT) + 1)] = (string) file_get_contents($entry->getPathname());
            }
        }

        return $files;
    }
}
