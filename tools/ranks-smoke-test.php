<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This smoke test is CLI-only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/class/RanksRepository.php';

try {
    $repository = new RanksRepository();
    $health = $repository->ping();
    $leaderboard = $repository->getLeaderboard('points', 'DESC', '', 1, 10);

    if (($health['database'] ?? null) !== 'ok'
        || ($health['table'] ?? null) !== 'lvl_base'
        || !is_numeric($health['row_count'] ?? null)
        || !isset($leaderboard['players'], $leaderboard['top_three'], $leaderboard['stats'])) {
        throw new RuntimeException('Ranks API returned an invalid smoke-test payload.');
    }

    echo "PASS: Signed Ranks API request and GLOBAL_ranks.lvl_base read access succeeded.\n";
    echo 'Rows: ' . (int)$health['row_count'] . PHP_EOL;
    echo 'Leaderboard rows: ' . count($leaderboard['players']) . PHP_EOL;
    echo 'Top-three rows: ' . count($leaderboard['top_three']) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
