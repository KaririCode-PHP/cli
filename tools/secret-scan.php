<?php

declare(strict_types=1);

/*
 * Secret scan (Spec V4.3.3 §32): fails when a repository text file holds
 * something shaped like a credential. vendor/, build/ and .git/ are skipped.
 *
 * Usage: php tools/secret-scan.php [root]
 */

$root     = rtrim($argv[1] ?? dirname(__DIR__), '/');
$self     = realpath(__FILE__);
$patterns = [
    'private key'     => '/-----BEGIN (?:RSA |EC |DSA |OPENSSH )?PRIVATE KEY-----/',
    'AWS access key'  => '/\bAKIA[0-9A-Z]{16}\b/',
    'GitHub token'    => '/\bgh[pousr]_[A-Za-z0-9]{36,}\b/',
    'Slack token'     => '/\bxox[abprs]-[A-Za-z0-9-]{10,}\b/',
    'assigned secret' => '/\b(?:api[_-]?key|secret|password|passwd|token)\b["\']?\s*(?:=>|[:=])\s*["\'][^"\'\s]{12,}["\']/i',
];
$findings = [];

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    $path = $file->getPathname();
    if (preg_match('#/(?:vendor|build|\.git)/#', $path) === 1 || realpath($path) === $self || $file->getSize() > 1_000_000) {
        continue;
    }
    $content = (string) file_get_contents($path);
    if (str_contains($content, "\0")) {
        continue;
    }
    foreach ($patterns as $label => $pattern) {
        if (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE) === 1) {
            $findings[] = sprintf('%s:%d %s', substr($path, strlen($root) + 1), substr_count($content, "\n", 0, $match[0][1]) + 1, $label);
        }
    }
}

echo $findings === [] ? "secret-scan: no findings\n" : implode("\n", $findings) . "\n";
exit($findings === [] ? 0 : 1);
