<?php

declare(strict_types=1);

namespace KaririCode\Cli\ValueObject;

/**
 * One registered route as reported by a RouteSource.
 *
 * ARFA 1.3: P1 (immutable state). Spec V4.3.3: Value Object, final readonly.
 *
 * Preconditions: values come from a RouteSource implementation.
 * Thread-safety: immutable; safe to share between fibers.
 * Side effects: none.
 *
 * Example:
 *   new RouteEntry(method: 'GET', path: '/users/{id}', handler: 'UserController::show', name: 'users.show');
 *
 * References: ADR-011.
 * @see https://www.php.net/manual/en/language.oop5.properties.php#language.oop5.properties.readonly-properties
 */
final readonly class RouteEntry
{
    public function __construct(
        public string $method,
        public string $path,
        public string $handler,
        public string $name = '',
    ) {}
}
