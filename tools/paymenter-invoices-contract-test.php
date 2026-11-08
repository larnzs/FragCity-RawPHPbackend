<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/class/PaymenterInvoiceApiClient.php';

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
    is_array($upgradeResult) && count($upgradeResult['items']) === 1,
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

echo "All Paymenter invoice contract tests passed.\n";
