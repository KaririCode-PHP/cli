<?php

declare(strict_types=1);

/*
 * CycloneDX 1.5 SBOM built from composer.lock (Spec V4.3.3 §32).
 * The timestamp honours SOURCE_DATE_EPOCH for reproducible output.
 *
 * Usage: php tools/sbom.php [composer.lock] [sbom.cdx.json]
 */

$root   = dirname(__DIR__);
$lock   = $argv[1] ?? $root . '/composer.lock';
$target = $argv[2] ?? $root . '/sbom.cdx.json';

if (!is_file($lock)) {
    fwrite(STDERR, "sbom: composer.lock not found; run composer update and commit the lock (§32).\n");
    exit(1);
}

$data     = json_decode((string) file_get_contents($lock), true, 512, JSON_THROW_ON_ERROR);
$manifest = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$hash     = sha1((string) ($data['content-hash'] ?? ''));
$epoch    = getenv('SOURCE_DATE_EPOCH');

$component = static function (array $package, string $scope): array {
    $purl = 'pkg:composer/' . $package['name'] . '@' . $package['version'];

    return array_filter([
        'type'     => 'library',
        'bom-ref'  => $purl,
        'name'     => $package['name'],
        'version'  => $package['version'],
        'scope'    => $scope,
        'purl'     => $purl,
        'licenses' => array_map(static fn(string $id): array => ['license' => ['id' => $id]], $package['license'] ?? []),
        'hashes'   => ($package['dist']['shasum'] ?? '') !== '' ? [['alg' => 'SHA-1', 'content' => $package['dist']['shasum']]] : [],
    ], static fn(mixed $value): bool => $value !== []);
};

$bom = [
    'bomFormat'    => 'CycloneDX',
    'specVersion'  => '1.5',
    'serialNumber' => sprintf('urn:uuid:%s-%s-5%s-a%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 13, 3), substr($hash, 17, 3), substr($hash, 20, 12)),
    'version'      => 1,
    'metadata'     => [
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z', $epoch !== false ? (int) $epoch : time()),
        'component' => ['type' => 'library', 'name' => $manifest['name'], 'purl' => 'pkg:composer/' . $manifest['name']],
    ],
    'components' => [
        ...array_map(static fn(array $p): array => $component($p, 'required'), $data['packages'] ?? []),
        ...array_map(static fn(array $p): array => $component($p, 'excluded'), $data['packages-dev'] ?? []),
    ],
];

file_put_contents($target, json_encode($bom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
echo 'sbom: ' . count($bom['components']) . " components written to {$target}\n";
