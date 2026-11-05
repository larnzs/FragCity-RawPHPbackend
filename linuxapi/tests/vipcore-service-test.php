<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/VIPCore/VipCoreService.php';

$tests = [];
$check = static function (string $name, bool $condition) use (&$tests): void {
    $tests[$name] = $condition;
};

$check(
    'SteamID64 base maps to account ID zero',
    VipCoreService::accountIdFromSteamId64('76561197960265728') === 0
);
$check(
    'Known SteamID64 converts to numeric account ID one',
    VipCoreService::accountIdFromSteamId64('76561197960265729') === 1
);
$check(
    'Only Bronze, Silver and Gold are grantable',
    VipCoreService::groupForLevel(1) === 'BRONZE'
    && VipCoreService::groupForLevel(2) === 'SILVER'
    && VipCoreService::groupForLevel(3) === 'GOLD'
);
$check(
    'All six configured groups can be read from VIPCore',
    VipCoreService::levelForGroup('BRONZE') === 1
    && VipCoreService::levelForGroup('SILVER') === 2
    && VipCoreService::levelForGroup('GOLD') === 3
    && VipCoreService::levelForGroup('PLATINUM') === 4
    && VipCoreService::levelForGroup('EMERALD') === 5
    && VipCoreService::levelForGroup('DIAMOND') === 6
);

$check(
    'VIP test grants require an exact allowlisted SteamID64',
    VipCoreService::isTestGrantAllowed('76561197960265729', ['76561197960265729'])
    && !VipCoreService::isTestGrantAllowed('76561197960265729', [])
    && !VipCoreService::isTestGrantAllowed('76561197960265730', ['76561197960265729'])
    && !VipCoreService::isTestGrantAllowed('invalid', ['invalid'])
);

$invalidSteamIdRejected = false;
try {
    VipCoreService::accountIdFromSteamId64('not-a-steamid');
} catch (InvalidArgumentException) {
    $invalidSteamIdRejected = true;
}
$check('Malformed SteamID64 is rejected', $invalidSteamIdRejected);

$lockedTierRejected = false;
try {
    VipCoreService::groupForLevel(4);
} catch (InvalidArgumentException) {
    $lockedTierRejected = true;
}
$check('Inactive tier 4 cannot be granted in test mode', $lockedTierRejected);

$failed = false;
foreach ($tests as $name => $passed) {
    echo ($passed ? 'PASS: ' : 'FAIL: ') . $name . PHP_EOL;
    if (!$passed) {
        $failed = true;
    }
}
exit($failed ? 1 : 0);
