<?php

declare(strict_types=1);

namespace KaririCode\Cli\Inspector;

use KaririCode\Cli\Contract\Inspector;
use KaririCode\Cli\Contract\RouteSource;
use KaririCode\Cli\ValueObject\InspectionResult;

/**
 * Lists registered routes through the RouteSource port.
 *
 * ARFA 1.3: P4. Spec V4.3.3: stateless service; replaces the duck-typed
 * `object` router of 4.0 (§31, ADR-011).
 *
 * Preconditions: none; without a source the result carries a status line.
 * Thread-safety: holds only the injected port.
 * Side effects: none beyond the port's own reads.
 *
 * Example:
 *   $result = (new RouterInspector($routeSource))->inspect();
 *
 * References: ADR-011.
 * @see https://www.php-fig.org/psr/psr-15/
 */
final class RouterInspector implements Inspector
{
    public string $componentName {
        get => 'Router';
    }

    public function __construct(
        private readonly ?RouteSource $source = null,
    ) {}

    #[\Override]
    public function inspect(): InspectionResult
    {
        if ($this->source === null) {
            return new InspectionResult(
                componentName: $this->componentName,
                rows: [],
                summary: ['status' => 'No RouteSource registered (ADR-011)'],
            );
        }

        $rows     = [];
        $byMethod = [];

        foreach ($this->source->routes() as $route) {
            $method            = strtoupper($route->method);
            $rows[]            = ['method' => $method, 'path' => $route->path, 'handler' => $route->handler, 'name' => $route->name];
            $byMethod[$method] = ($byMethod[$method] ?? 0) + 1;
        }

        ksort($byMethod);

        return new InspectionResult(
            componentName: $this->componentName,
            rows: $rows,
            summary: ['total' => count($rows), ...$byMethod],
        );
    }
}
