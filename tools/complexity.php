<?php

declare(strict_types=1);

/*
 * Spec V4.3.3 §13 budgets per function or method, measured from PHP tokens:
 * cyclomatic ≤ 10 (if, elseif, loops, case, catch, &&, ||, and, or, xor,
 * ternary, match arms − 1; `??` is not counted, as in PHPMD), control-flow
 * nesting ≤ 3, parameters ≤ 4. Accepted breaches live, with their reason,
 * in tools/complexity-baseline.php. Cognitive complexity ≤ 15 is enforced
 * separately by phpstan-complexity.neon.
 *
 * Usage: php tools/complexity.php src [--report]
 */

const BUDGET    = ['cyclomatic' => 10, 'nesting' => 3, 'parameters' => 4];
const DECISIONS = [T_IF, T_ELSEIF, T_FOR, T_FOREACH, T_WHILE, T_CASE, T_CATCH, T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR, T_LOGICAL_XOR];
const CONTROL   = [T_IF, T_ELSE, T_ELSEIF, T_FOR, T_FOREACH, T_WHILE, T_DO, T_SWITCH, T_TRY, T_CATCH, T_FINALLY, T_MATCH];
const OPENERS   = ['(', '[', '{', '#[', '${'];

/** @return list<PhpToken> */
function significant(string $code): array
{
    return array_values(array_filter(PhpToken::tokenize($code), static fn(PhpToken $t): bool => !$t->isIgnorable()));
}

/** @param list<PhpToken> $t @return array{int, int} parameter count and index after ')' */
function parameters(array $t, int $i): array
{
    [$depth, $commas, $last] = [0, 0, ''];
    for ($n = count($t); $i < $n; $i++) {
        $text = $t[$i]->text;
        if (in_array($text, OPENERS, true) || $t[$i]->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
            $depth++;
        } elseif (in_array($text, [')', ']', '}'], true) && --$depth === 0) {
            break;
        } elseif ($text === ',' && $depth === 1) {
            $commas++;
        }
        $last = $depth === 1 && $text !== '(' ? $text : $last;
    }
    $count = $last === '' ? 0 : $commas + 1 - ($last === ',' ? 1 : 0);

    return [$count, $i + 1];
}

/** @param list<PhpToken> $t @return array{int, int, int} cyclomatic, nesting, index of the closing brace */
function body(array $t, int $i): array
{
    [$cyclomatic, $nesting, $max, $parens, $pending, $stack] = [1, 0, 0, 0, null, []];
    for ($n = count($t); $i < $n; $i++) {
        $token = $t[$i];
        $text  = $token->text;
        $prev  = $t[$i - 1]->text ?? '';
        if ($token->is(CONTROL)) {
            $pending = ['depth' => $parens, 'match' => $token->is(T_MATCH)];
        }
        if ($token->is(DECISIONS) || ($text === '?' && !in_array($prev, ['(', ',', ':', '|'], true))) {
            $cyclomatic++;
        }
        if ($text === '(' || $text === '[' || $text === '#[') {
            $parens++;
        } elseif ($text === ')' || $text === ']') {
            $parens--;
        } elseif ($text === '{' || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
            $control = $text === '{' && $pending !== null && $pending['depth'] === $parens;
            $stack[] = ['control' => $control, 'match' => $control && $pending['match'], 'arms' => 0, 'parens' => $parens];
            $pending = $control ? null : $pending;
            $nesting += $control ? 1 : 0;
            $max      = max($max, $nesting);
        } elseif ($text === '}') {
            $frame       = array_pop($stack);
            $nesting    -= $frame['control'] ? 1 : 0;
            $cyclomatic += $frame['match'] ? max(0, $frame['arms'] - 1) : 0;
            if ($stack === []) {
                return [$cyclomatic, $max - 1, $i];
            }
        } elseif ($token->is(T_DOUBLE_ARROW) && $stack !== [] && end($stack)['match'] && end($stack)['parens'] === $parens) {
            $stack[array_key_last($stack)]['arms']++;
        }
    }

    return [$cyclomatic, max(0, $max - 1), $i];
}

/** @return array<string, array{cyclomatic: int, nesting: int, parameters: int}> */
function measure(string $code): array
{
    [$t, $type, $result] = [significant($code), '', []];
    for ($i = 0, $n = count($t); $i < $n; $i++) {
        if ($t[$i]->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM]) && ($t[$i + 1] ?? null)?->is(T_STRING)) {
            $type = $t[$i + 1]->text;
        }
        if (!$t[$i]->is(T_FUNCTION) || !($t[$i + 1] ?? null)?->is(T_STRING)) {
            continue;
        }
        $name          = $type . '::' . $t[$i + 1]->text;
        [$params, $j]  = parameters($t, $i + 2);
        while ($j < $n && $t[$j]->text !== '{' && $t[$j]->text !== ';') {
            $j++;
        }
        if ($j >= $n || $t[$j]->text === ';') {
            $result[$name] = ['cyclomatic' => 1, 'nesting' => 0, 'parameters' => $params];
            continue;
        }
        // The method body itself is a control frame of depth 1; body() reports depth − 1.
        $t[$j - 1]          = $t[$j - 1]->is(CONTROL) ? $t[$j - 1] : new PhpToken(T_DO, 'do');
        [$cc, $nest, $end]  = body($t, $j - 1);
        $result[$name]      = ['cyclomatic' => $cc, 'nesting' => $nest, 'parameters' => $params];
        $i                  = $end;
    }

    return $result;
}

$root     = $argv[1] ?? 'src';
$report   = in_array('--report', $argv, true);
$baseline = require __DIR__ . '/complexity-baseline.php';
$failures = [];
$count    = 0;

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
        continue;
    }
    foreach (measure((string) file_get_contents($file->getPathname())) as $function => $metrics) {
        $count++;
        if ($report) {
            printf("%-60s cc=%-3d nest=%d params=%d\n", $function, $metrics['cyclomatic'], $metrics['nesting'], $metrics['parameters']);
        }
        foreach (BUDGET as $metric => $limit) {
            $allowed = $baseline[$function][$metric] ?? $limit;
            if ($metrics[$metric] > $allowed) {
                $failures[] = sprintf('%s: %s %d exceeds %d', $function, $metric, $metrics[$metric], $allowed);
            }
        }
    }
}

echo $failures === [] ? "complexity: {$count} functions within budget\n" : implode("\n", $failures) . "\n";
exit($failures === [] ? 0 : 1);
