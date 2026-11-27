<?php

declare(strict_types=1);

namespace KaririCode\Cli\Integration;

use KaririCode\Cli\Command\Check\CheckArfaCommand;
use KaririCode\Cli\Command\Check\CheckEcosystemCommand;
use KaririCode\Cli\Command\Inspect\InspectAstCommand;
use KaririCode\Cli\Command\Inspect\InspectContainerCommand;
use KaririCode\Cli\Command\Inspect\InspectDiagnosticsCommand;
use KaririCode\Cli\Command\Inspect\InspectRouterCommand;
use KaririCode\Cli\Command\Make\MakeCommandCommand;
use KaririCode\Cli\Command\Make\MakeDpoCommand;
use KaririCode\Cli\Command\Make\MakeEntityCommand;
use KaririCode\Cli\Command\Make\MakeGrammarCommand;
use KaririCode\Cli\Command\Make\MakeRepositoryCommand;
use KaririCode\Cli\Command\Make\MakeServiceCommand;
use KaririCode\Cli\Command\Make\MakeValueObjectCommand;
use KaririCode\Cli\Command\Make\MakeVisitorCommand;
use KaririCode\Cli\Contract\BindingSource;
use KaririCode\Cli\Contract\ComplianceEngine;
use KaririCode\Cli\Contract\DiagnosticsSource;
use KaririCode\Cli\Contract\RouteSource;
use KaririCode\Cli\Contract\SyntaxTreeDumper;
use KaririCode\Cli\Generator\GeneratorRegistry;
use KaririCode\Cli\Inference\FieldInferenceEngine;
use KaririCode\Cli\Inspector\ContainerInspector;
use KaririCode\Cli\Inspector\RegexComplianceEngine;
use KaririCode\Cli\Inspector\RouterInspector;
use KaririCode\Cli\Template\OutputRenderer;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Core\Application;
use KaririCode\Console\Provider\ConsoleServiceProvider;
use KaririCode\Contract\DI\BindableContainer;
use KaririCode\Contract\DI\ServiceProvider;

/**
 * Registers the CLI services and its fourteen commands.
 *
 * ARFA 1.3: P4. Spec V4.3.3: implements kariricode/contract's
 * DI\ServiceProvider (§31). Optional adapters — BindingSource, RouteSource,
 * SyntaxTreeDumper, DiagnosticsSource, ComplianceEngine — are taken from the
 * container when bound and left null otherwise (ADR-011).
 *
 * Preconditions: register() only binds and never resolves; factories expect
 * the container to pass itself, as kariricode/dependency-injection does.
 * Thread-safety: no state; static helpers are pure.
 * Side effects: register() binds services; buildApplication() creates a
 * Console Application.
 *
 * Example:
 *   CliServiceProvider::buildApplication()->dispatch(new ArgvInput(), new ConsoleOutput());
 *
 * References: ADR-001, ADR-011.
 * @see https://www.php-fig.org/psr/psr-11/
 */
final class CliServiceProvider implements ServiceProvider
{
    public function register(BindableContainer $container): void
    {
        $container->bind(GeneratorRegistry::class, static fn(): GeneratorRegistry => new GeneratorRegistry(), singleton: true);
        $container->bind(FieldInferenceEngine::class, static fn(): FieldInferenceEngine => new FieldInferenceEngine(), singleton: true);
        $container->bind(OutputRenderer::class, static fn(): OutputRenderer => new OutputRenderer(), singleton: true);

        foreach (array_keys(self::commands()) as $class) {
            $container->bind(
                $class,
                static fn(BindableContainer $resolved): Command => self::commands($resolved)[$class],
                singleton: true,
            );
        }
    }

    public function boot(): void
    {
    }

    /** Standalone application for bin/kcli; adapters come from $container when given. */
    public static function buildApplication(?BindableContainer $container = null): Application
    {
        $application = ConsoleServiceProvider::buildApplication('KaririCode CLI', '4.1.0');

        foreach (self::commands($container) as $command) {
            $application->add($command);
        }

        return $application;
    }

    /** @return array<class-string<Command>, Command> */
    public static function commands(?BindableContainer $container = null): array
    {
        $registry  = self::optional($container, GeneratorRegistry::class) ?? new GeneratorRegistry();
        $inference = self::optional($container, FieldInferenceEngine::class) ?? new FieldInferenceEngine();
        $renderer  = self::optional($container, OutputRenderer::class) ?? new OutputRenderer();
        $engine    = self::optional($container, ComplianceEngine::class) ?? new RegexComplianceEngine();

        $commands = [
            new MakeDpoCommand($registry, $inference),
            new MakeCommandCommand($registry),
            new MakeEntityCommand($registry, $inference),
            new MakeValueObjectCommand($registry, $inference),
            new MakeRepositoryCommand($registry),
            new MakeServiceCommand($registry),
            new MakeGrammarCommand($registry),
            new MakeVisitorCommand($registry),
            new InspectContainerCommand(new ContainerInspector(self::optional($container, BindingSource::class)), $renderer),
            new InspectRouterCommand(new RouterInspector(self::optional($container, RouteSource::class)), $renderer),
            new InspectAstCommand(self::optional($container, SyntaxTreeDumper::class)),
            new InspectDiagnosticsCommand($renderer, self::optional($container, DiagnosticsSource::class)),
            new CheckArfaCommand($renderer, $engine),
            new CheckEcosystemCommand(),
        ];

        $byClass = [];

        foreach ($commands as $command) {
            $byClass[$command::class] = $command;
        }

        return $byClass;
    }

    /**
     * @template T of object
     * @param  class-string<T> $id
     * @return T|null
     */
    private static function optional(?BindableContainer $container, string $id): ?object
    {
        if ($container === null || !$container->has($id)) {
            return null;
        }

        $service = $container->get($id);

        return $service instanceof $id ? $service : null;
    }
}
