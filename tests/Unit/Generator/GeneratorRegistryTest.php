<?php

declare(strict_types=1);

namespace KaririCode\Cli\Tests\Unit\Generator;

use KaririCode\Cli\Enum\GeneratorTarget;
use KaririCode\Contract\Exception\ExceptionInterface;
use KaririCode\Cli\Generator\DpoGenerator;
use KaririCode\Cli\Generator\GeneratorRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GeneratorRegistry::class)]
final class GeneratorRegistryTest extends TestCase
{
    public function testGetReturnsDefaultDpoGenerator(): void
    {
        $registry = new GeneratorRegistry();
        self::assertInstanceOf(DpoGenerator::class, $registry->get(GeneratorTarget::Dpo));
    }

    public function testAllDefaultTargetsAreRegistered(): void
    {
        $registry = new GeneratorRegistry();

        $targets = [
            GeneratorTarget::Dpo,
            GeneratorTarget::Command,
            GeneratorTarget::ValueObject,
            GeneratorTarget::Entity,
            GeneratorTarget::Service,
            GeneratorTarget::Repository,
        ];

        foreach ($targets as $target) {
            self::assertNotNull($registry->get($target));
        }
    }

    public function testCustomGeneratorOverridesDefault(): void
    {
        $registry  = new GeneratorRegistry();
        $custom    = new DpoGenerator(); // same type, new instance

        $registry->register(GeneratorTarget::Dpo, $custom);

        self::assertSame($custom, $registry->get(GeneratorTarget::Dpo));
    }

    public function testUnregisteredTargetThrowsContractException(): void
    {
        $registry = new GeneratorRegistry();

        // GeneratorTarget::Transformer has no default generator
        $this->expectException(ExceptionInterface::class);
        $registry->get(GeneratorTarget::Transformer);
    }

    public function testRegisterAfterFirstUseThrowsRegistrySealed(): void
    {
        $registry = new GeneratorRegistry();
        $registry->get(GeneratorTarget::Dpo);

        $this->expectException(ExceptionInterface::class);
        $this->expectExceptionCode(8642);

        $registry->register(GeneratorTarget::Dpo, new DpoGenerator());
    }
}
