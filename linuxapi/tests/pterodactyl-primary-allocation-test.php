<?php
declare(strict_types=1);

/**
 * Regression test for Pterodactyl Application API primary Allocation mapping.
 *
 * Run from the repository root:
 * php linuxapi/tests/pterodactyl-primary-allocation-test.php
 *
 * This test uses a local response fixture only. It does not contact Pterodactyl,
 * send A2S packets, or require any API keys or runtime configuration.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once __DIR__ . '/../src/PterodactylStandby/PterodactylApplicationClient.php';

$reflection = new ReflectionClass(FragCityPterodactylApplicationClient::class);
$client = $reflection->newInstanceWithoutConstructor();

$privateValues = [
    'nodeId' => 1,
    'allowedEggIds' => [],
    'allowedImages' => ['ghcr.io/k4ryuu/cs2-egg-go'],
];

foreach ($privateValues as $propertyName => $value) {
    $property = $reflection->getProperty($propertyName);
    $property->setAccessible(true);
    $property->setValue($client, $value);
}

$mapCandidate = $reflection->getMethod('toCandidate');
$mapCandidate->setAccessible(true);

$serverAttributes = [
    'id' => 123,
    'uuid' => '11111111-2222-3333-4444-555555555555',
    'identifier' => 'a1b2c3d4',
    'name' => 'FragCity CS2 Test',
    'node' => 1,
    'egg' => 17,
    // Pterodactyl Application API exposes the primary Allocation this way.
    'allocation' => 77,
    'container' => [
        'image' => 'ghcr.io/k4ryuu/cs2-egg-go:stable',
        'installed' => 1,
    ],
    'suspended' => false,
    'relationships' => [
        'allocations' => [
            'data' => [
                // Deliberately no is_default property: Application API allocations
                // are mapped by their ID against the server's allocation field.
                ['attributes' => ['id' => 76, 'port' => 27084]],
                ['attributes' => ['id' => 77, 'port' => 27085]],
                ['attributes' => ['id' => 78, 'port' => 27086]],
            ],
        ],
    ],
];

$candidate = $mapCandidate->invoke($client, $serverAttributes);
if (!is_array($candidate)
    || (int)($candidate['allocation_id'] ?? 0) !== 77
    || (int)($candidate['game_port'] ?? 0) !== 27085) {
    fwrite(STDERR, "FAIL: primary Application API Allocation ID was not selected.\n");
    exit(1);
}

echo "PASS: primary Allocation was selected by matching server allocation ID (77 -> UDP 27085).\n";

$missingRelationship = $serverAttributes;
unset($missingRelationship['relationships']['allocations']);

try {
    $mapCandidate->invoke($client, $missingRelationship);
    fwrite(STDERR, "FAIL: missing Allocations relationship was silently accepted.\n");
    exit(1);
} catch (RuntimeException $e) {
    if (!str_contains($e->getMessage(), 'read access to Allocations')) {
        fwrite(STDERR, "FAIL: missing Allocations relationship returned an unexpected diagnostic.\n");
        exit(1);
    }
}

echo "PASS: missing Allocations permission/relationship fails with an actionable diagnostic.\n";
