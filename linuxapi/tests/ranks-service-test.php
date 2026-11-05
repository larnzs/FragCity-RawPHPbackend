<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Ranks/RanksService.php';

$tests = [];
$check = static function (string $name, bool $condition) use (&$tests): void {
    $tests[$name] = $condition;
};

$check(
    'Known Steam2 ID converts to the expected SteamID64',
    RanksService::steam2ToSteamId64('STEAM_1:1:127247282') === '76561198214760293'
);
$check(
    'Known SteamID64 converts to the exact Ranks Steam2 key',
    RanksService::steamId64ToSteam2('76561198214760293') === 'STEAM_1:1:127247282'
);
$check(
    'Ranks sort allowlist matches the public leaderboard controls',
    RanksService::allowedSorts() === [
        'points', 'kills', 'deaths', 'assists', 'headshots', 'wins', 'playtime',
    ]
);
$check(
    'Ranks page sizes match the website controls',
    RanksService::allowedPageSizes() === [10, 15, 25, 50, 100]
);

$invalidSteam2Rejected = false;
try {
    RanksService::steam2ToSteamId64('not-a-steam-id');
} catch (InvalidArgumentException) {
    $invalidSteam2Rejected = true;
}
$check('Malformed Steam2 values are rejected', $invalidSteam2Rejected);

$invalidSteam64Rejected = false;
try {
    RanksService::steamId64ToSteam2('76561190000000000');
} catch (InvalidArgumentException) {
    $invalidSteam64Rejected = true;
}
$check('Out-of-range SteamID64 values are rejected', $invalidSteam64Rejected);

$invalidSortRejected = false;
try {
    RanksService::normalizeSort('steam; DROP TABLE lvl_base');
} catch (InvalidArgumentException) {
    $invalidSortRejected = true;
}
$check('Caller-supplied SQL expressions cannot become sort fields', $invalidSortRejected);

$invalidPageSizeRejected = false;
try {
    RanksService::normalizePageSize(999);
} catch (InvalidArgumentException) {
    $invalidPageSizeRejected = true;
}
$check('Unsupported leaderboard page sizes are rejected', $invalidPageSizeRejected);

$failed = false;
foreach ($tests as $name => $passed) {
    echo ($passed ? 'PASS: ' : 'FAIL: ') . $name . PHP_EOL;
    if (!$passed) {
        $failed = true;
    }
}
exit($failed ? 1 : 0);
