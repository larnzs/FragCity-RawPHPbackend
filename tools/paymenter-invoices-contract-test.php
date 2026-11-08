<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/class/PaymenterInvoiceApiClient.php';
require_once dirname(__DIR__) . '/class/PaymenterInvoiceContext.php';

$clientReflection = new ReflectionClass(PaymenterInvoiceApiClient::class);
$client = $clientReflection->newInstanceWithoutConstructor();

$normalise = $clientReflection->getMethod('normaliseInvoice');
$normalise->setAccessible(true);

$makeInvoice = static function (array $items): array {
    $references = [];
    $included = [];

    foreach ($items as $index => $attributes) {
        $id = (string) ($index + 1);
        $references[] = [
            'type' => 'invoice-items',
            'id' => $id,
        ];
        $included['invoice-items:' . $id] = [
            'type' => 'invoice-items',
            'id' => $id,
            'attributes' => $attributes,
        ];
    }

    return [
        [
            'id' => '41',
            'attributes' => [
                'status' => 'pending',
                'currency_code' => 'EUR',
                'due_at' => '2026-11-15',
                'created_at' => '2026-11-08T00:00:00Z',
            ],
            'relationships' => [
                'items' => ['data' => $references],
            ],
        ],
        $included,
    ];
};

$assert = static function (bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }

    echo "PASS: {$label}\n";
};

[$serviceInvoice, $serviceIncluded] = $makeInvoice([[
    'description' => 'FragCity CS2 Server',
    'quantity' => '1',
    'price' => '19.90',
    'reference_type' => 'App\\Models\\Service',
    'reference_id' => '22',
]]);
$serviceResult = $normalise->invoke($client, $serviceInvoice, $serviceIncluded);
$assert(
    is_array($serviceResult)
    && $serviceResult['invoice_id'] === 41
    && count($serviceResult['items']) === 1
    && $serviceResult['items'][0]['reference_kind'] === 'service'
    && $serviceResult['items'][0]['reference_id'] === 22
    && $serviceResult['subtotal'] === 19.9,
    'linked Paymenter Service invoice is included'
);

[$upgradeInvoice, $upgradeIncluded] = $makeInvoice([[
    'description' => 'Server upgrade',
    'quantity' => '1',
    'price' => '5.00',
    'reference_type' => 'App\\Models\\ServiceUpgrade',
    'reference_id' => '23',
]]);
$upgradeResult = $normalise->invoke($client, $upgradeInvoice, $upgradeIncluded);
$assert(
    is_array($upgradeResult)
    && count($upgradeResult['items']) === 1
    && $upgradeResult['items'][0]['reference_kind'] === 'service_upgrade'
    && $upgradeResult['items'][0]['reference_id'] === 23,
    'linked ServiceUpgrade invoice is included'
);

[$manualInvoice, $manualIncluded] = $makeInvoice([[
    'description' => 'Manually entered server charge',
    'quantity' => '2',
    'price' => '10.00',
    'reference_type' => null,
    'reference_id' => null,
]]);
$manualResult = $normalise->invoke($client, $manualInvoice, $manualIncluded);
$assert(
    is_array($manualResult)
    && count($manualResult['items']) === 1
    && $manualResult['items'][0]['reference_kind'] === 'manual'
    && $manualResult['items'][0]['reference_id'] === null
    && $manualResult['subtotal'] === 20.0,
    'manual invoice lines without model references are included'
);

[$creditInvoice, $creditIncluded] = $makeInvoice([[
    'description' => 'Account credit',
    'quantity' => '1',
    'price' => '10.00',
    'reference_type' => 'App\\Models\\Credit',
    'reference_id' => '24',
]]);
$creditResult = $normalise->invoke($client, $creditInvoice, $creditIncluded);
$assert($creditResult === null, 'Credit-only invoices are excluded from server orders');

[$emptyInvoice, $emptyIncluded] = $makeInvoice([]);
$emptyResult = $normalise->invoke($client, $emptyInvoice, $emptyIncluded);
$assert($emptyResult === null, 'empty invoices do not break the customer invoice list');

$ownerCheck = $clientReflection->getMethod('assertInvoiceOwner');
$ownerCheck->setAccessible(true);
$ownerResource = [
    'id' => '41',
    'relationships' => [
        'user' => [
            'data' => [
                'type' => 'users',
                'id' => '5',
            ],
        ],
    ],
];
$ownerIncluded = [
    'users:5' => [
        'type' => 'users',
        'id' => '5',
        'attributes' => ['email' => 'customer@example.com'],
    ],
];
$ownerCheck->invoke($client, $ownerResource, $ownerIncluded, 5, 'customer@example.com');
$assert(true, 'invoice owner ID and email exact match is accepted');

try {
    $ownerCheck->invoke($client, $ownerResource, $ownerIncluded, 6, 'customer@example.com');
    $assert(false, 'invoice owner ID mismatch is rejected');
} catch (ReflectionException|RuntimeException) {
    $assert(true, 'invoice owner ID mismatch is rejected');
}

try {
    $ownerCheck->invoke($client, $ownerResource, $ownerIncluded, 5, 'other@example.com');
    $assert(false, 'invoice owner email mismatch is rejected');
} catch (ReflectionException|RuntimeException) {
    $assert(true, 'invoice owner email mismatch is rejected');
}

$contextInvoices = [
    [
        'invoice_id' => 41,
        'items' => [
            [
                'reference_kind' => 'service',
                'reference_id' => 22,
                'description' => 'CS2 service renewal',
            ],
            [
                'reference_kind' => 'service_upgrade',
                'reference_id' => 23,
                'description' => 'CS2 service upgrade',
            ],
            [
                'reference_kind' => 'manual',
                'reference_id' => null,
                'description' => 'Manual server fee',
            ],
        ],
    ],
];
$serverRows = [[
    'service_id' => 22,
    'product_name' => 'FragCity CS2',
    'service_status' => 'active',
    'state' => 'ready',
    'service_upgrade_ids' => [23],
    'server' => [
        'name' => 'My Test CS2',
        'allocation_ip' => '203.0.113.10',
        'allocation_alias' => '',
        'allocation_port' => 27015,
        'node_name' => 'Node A',
        'location_long' => 'Test Location',
        'server_status' => 'running',
        'uuid' => 'must-not-be-exposed',
        'identifier' => 'must-not-be-exposed',
    ],
]];
$enriched = PaymenterInvoiceContext::attach($contextInvoices, $serverRows, true);
$enrichedItems = $enriched[0]['items'] ?? [];
$assert(
    ($enrichedItems[0]['association_status'] ?? '') === 'linked'
    && ($enrichedItems[0]['server']['service_id'] ?? 0) === 22
    && ($enrichedItems[0]['server']['host'] ?? '') === '203.0.113.10'
    && !array_key_exists('uuid', $enrichedItems[0]['server'] ?? []),
    'Service line links to the exact customer-owned server using an allow-listed summary'
);
$assert(
    ($enrichedItems[1]['association_status'] ?? '') === 'linked'
    && ($enrichedItems[1]['related_service_id'] ?? 0) === 22,
    'ServiceUpgrade line maps through the service upgrade ID to its parent server'
);
$assert(
    ($enrichedItems[2]['association_status'] ?? '') === 'manual_unlinked'
    && ($enrichedItems[2]['server'] ?? null) === null,
    'manual invoice line is never guessed to belong to a server'
);
$unavailable = PaymenterInvoiceContext::attach($contextInvoices, [], false);
$assert(
    ($unavailable[0]['items'][0]['association_status'] ?? '') === 'lookup_unavailable',
    'temporary server lookup failure does not mislabel an invoice as unlinked'
);

echo "All Paymenter invoice contract tests passed.\n";
