<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once __DIR__ . '/../src/PterodactylStandby/A2SPlayerQuery.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
};

$packet = "\xFF\xFF\xFF\xFF\x44" . chr(2)
    . chr(0) . "FragCity Player\x00" . pack('V', 24) . pack('g', 3661.5)
    . chr(1) . "Negative Score\x00" . pack('V', 0xFFFFFFF9) . pack('g', 42.25);

$players = FragCityA2SPlayerQuery::parseResponse($packet);

$assert(count($players) === 2, 'A2S_PLAYER parser returns both player entries.');
$assert($players[0]['index'] === 0, 'Player index is parsed.');
$assert($players[0]['name'] === 'FragCity Player', 'Player name is parsed.');
$assert($players[0]['score'] === 24, 'Positive score is parsed.');
$assert($players[0]['connected_seconds'] === 3661.5, 'Connected duration is parsed as seconds.');
$assert($players[1]['score'] === -7, 'Signed negative score is parsed.');
$assert($players[1]['connected_seconds'] === 42.3, 'Connected duration is rounded to one decimal.');
$assert($players[0]['steam_id'] === null, 'SteamID is not fabricated from A2S_PLAYER.');
$assert($players[0]['faceit_level'] === null, 'FACEIT level remains unset until its API is integrated.');

$emptyPacket = "\xFF\xFF\xFF\xFF\x44" . chr(0);
$assert(FragCityA2SPlayerQuery::parseResponse($emptyPacket) === [], 'Empty A2S_PLAYER response is supported.');

$invalidRejected = false;
try {
    FragCityA2SPlayerQuery::parseResponse("\xFF\xFF\xFF\xFF\x49");
} catch (RuntimeException $e) {
    $invalidRejected = true;
}
$assert($invalidRejected, 'Unexpected A2S response header is rejected.');

echo "PASS: A2S_PLAYER parser fixtures.\n";
