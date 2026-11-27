<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\ValueObject;

use KaririCode\Cli\Enum\GeneratorTarget;
use KaririCode\Cli\ValueObject\GeneratorContext;
use KaririCode\Contract\Exception\ExceptionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(GeneratorContext::class)]
final class GeneratorContextTest extends TestCase
{
    public function testDerivedPathsAndDate(): void
    {
        $context = $this->context('UserProfile', 'App\\Domain');

        self::assertSame('App\\Domain\\UserProfile', $context->fullyQualifiedClassName());
        self::assertSame('/tmp/out/UserProfile.php', $context->targetFilePath());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidClassNames(): iterable
    {
        yield 'kebab case' => ['user-profile'];
        yield 'lower camel' => ['userProfile'];
        yield 'leading digit' => ['9Lives'];
        yield 'empty' => [''];
        yield 'reserved list' => ['List'];
        yield 'reserved type' => ['String'];
        yield 'reserved keyword' => ['Readonly'];
    }

    #[DataProvider('invalidClassNames')]
    public function testRejectsInvalidClassNames(string $name): void
    {
        $this->expectException(ExceptionInterface::class);
        $this->expectExceptionCode(8900);

        $this->context($name, 'App');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNamespaces(): iterable
    {
        yield 'trailing separator' => ['App\\Domain\\'];
        yield 'leading separator' => ['\\App'];
        yield 'slash separator' => ['App/Domain'];
        yield 'double separator' => ['App\\\\Domain'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidNamespaces')]
    public function testRejectsInvalidNamespaces(string $namespace): void
    {
        $this->expectException(ExceptionInterface::class);
        $this->expectExceptionCode(8901);

        $this->context('User', $namespace);
    }

    private function context(string $class, string $namespace): GeneratorContext
    {
        return new GeneratorContext(
            className: $class,
            namespace: $namespace,
            target: GeneratorTarget::Entity,
            outputPath: '/tmp/out',
        );
    }
}
