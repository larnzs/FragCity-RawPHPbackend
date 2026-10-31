<?php
declare(strict_types=1);

/**
 * Read-only resolver for live A2S names.
 *
 * SimpleAdmin may apply a permanent display name from sa_renames after the
 * connecting player's original name has already been captured for
 * sa_players_ips. Resolve those forced display names first, then fall back
 * to the historical names table. No network identifier is fetched.
 */
final class SimpleAdminPlayerLookup
{
    private const MAX_NAMES = 128;
    private const MAX_NAME_LENGTH = 64;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int, array{name:string,steam_id:?string,status:string}>
     */
    public function resolveNames(mixed $input): array
    {
        if (!is_array($input) || count($input) > self::MAX_NAMES) {
            throw new InvalidArgumentException('Invalid player name list.');
        }

        $namesByKey = [];
        foreach ($input as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Player names must be strings.');
            }

            $name = trim($value);
            if ($name === '') {
                continue;
            }

            if (!preg_match('//u', $name)
                || str_contains($name, "\0")
                || mb_strlen($name, 'UTF-8') > self::MAX_NAME_LENGTH) {
                throw new InvalidArgumentException('Invalid player name.');
            }

            $namesByKey[self::normalizeNameKey($name)] = $name;
        }

        if ($namesByKey === []) {
            return [];
        }

        $names = array_values($namesByKey);
        $requestedKeys = array_fill_keys(array_keys($namesByKey), true);

        // A permanent SimpleAdmin rename is the live name shown by A2S.
        // The IP-history row can still contain the pre-rename name.
        $renamedCandidates = $this->queryRenamedCandidates($names, $requestedKeys);
        $historyCandidates = $this->queryHistoryCandidates($names, $requestedKeys);

        $matches = [];
        foreach ($names as $name) {
            $nameKey = self::normalizeNameKey($name);
            $steamIds = array_keys($renamedCandidates[$nameKey] ?? []);

            // Use historical connection names only when there is no
            // permanent-rename mapping for the observed live name.
            if ($steamIds === []) {
                $steamIds = array_keys($historyCandidates[$nameKey] ?? []);
            }

            if (count($steamIds) === 1) {
                $matches[] = [
                    'name' => $name,
                    'steam_id' => $steamIds[0],
                    'status' => 'resolved',
                ];
            } elseif (count($steamIds) > 1) {
                $matches[] = [
                    'name' => $name,
                    'steam_id' => null,
                    'status' => 'ambiguous',
                ];
            } else {
                $matches[] = [
                    'name' => $name,
                    'steam_id' => null,
                    'status' => 'not_found',
                ];
            }
        }

        return $matches;
    }

    /**
     * @param array<int, string> $names
     * @param array<string, bool> $requestedKeys
     * @return array<string, array<string, bool>>
     */
    private function queryRenamedCandidates(array $names, array $requestedKeys): array
    {
        [$placeholders, $params] = $this->buildNameParameters($names, 'rename_name');
        if ($placeholders === []) {
            return [];
        }

        $sql = '
            SELECT name AS candidate_name,
                   CAST(player_steamid AS CHAR) AS steam_id
            FROM sa_renames
            WHERE name IN (' . implode(', ', $placeholders) . ')
        ';

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Older SimpleAdmin schemas may not have migration 017 yet.
            // Only a missing-table error is optional; permission/DB errors
            // must still fail the request rather than silently hide a fault.
            $sqlState = (string)($e->errorInfo[0] ?? $e->getCode());
            $driverCode = (int)($e->errorInfo[1] ?? 0);
            if ($sqlState === '42S02' || $driverCode === 1146) {
                return [];
            }
            throw $e;
        }

        $candidates = [];
        foreach ($rows as $row) {
            $candidateName = is_string($row['candidate_name'] ?? null)
                ? trim($row['candidate_name'])
                : '';
            $steamId = self::normalizeSteamId($row['steam_id'] ?? null);
            if ($candidateName === '' || $steamId === null) {
                continue;
            }

            $key = self::normalizeNameKey($candidateName);
            if (!isset($requestedKeys[$key])) {
                continue;
            }
            $candidates[$key][$steamId] = true;
        }

        return $candidates;
    }

    /**
     * @param array<int, string> $names
     * @param array<string, bool> $requestedKeys
     * @return array<string, array<string, bool>>
     */
    private function queryHistoryCandidates(array $names, array $requestedKeys): array
    {
        [$placeholders, $params] = $this->buildNameParameters($names, 'history_name');
        if ($placeholders === []) {
            return [];
        }

        // Keep only the two most recently seen SteamIDs per stored name:
        // one is a unique match, two are enough to prove ambiguity.
        $sql = '
            SELECT candidate_name, steam_id
            FROM (
                SELECT candidate_name,
                       steam_id,
                       ROW_NUMBER() OVER (
                           PARTITION BY BINARY candidate_name
                           ORDER BY last_seen DESC
                       ) AS candidate_rank
                FROM (
                    SELECT MIN(name) AS candidate_name,
                           CAST(steamid AS CHAR) AS steam_id,
                           MAX(used_at) AS last_seen
                    FROM sa_players_ips
                    WHERE name IN (' . implode(', ', $placeholders) . ')
                    GROUP BY BINARY name, steamid
                ) AS grouped_candidates
            ) AS ranked_candidates
            WHERE candidate_rank <= 2
            ORDER BY candidate_name ASC, candidate_rank ASC
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $candidates = [];
        foreach ($rows as $row) {
            $candidateName = is_string($row['candidate_name'] ?? null)
                ? trim($row['candidate_name'])
                : '';
            $steamId = self::normalizeSteamId($row['steam_id'] ?? null);
            if ($candidateName === '' || $steamId === null) {
                continue;
            }

            $key = self::normalizeNameKey($candidateName);
            if (!isset($requestedKeys[$key])) {
                continue;
            }
            $candidates[$key][$steamId] = true;
        }

        return $candidates;
    }

    /**
     * @param array<int, string> $names
     * @return array{0:array<int,string>,1:array<string,string>}
     */
    private function buildNameParameters(array $names, string $prefix): array
    {
        $placeholders = [];
        $params = [];

        foreach ($names as $index => $name) {
            $placeholder = ':' . $prefix . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $name;
        }

        return [$placeholders, $params];
    }

    private static function normalizeNameKey(string $name): string
    {
        $name = trim($name);
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($name, Normalizer::FORM_C);
            if (is_string($normalized)) {
                $name = $normalized;
            }
        }

        return function_exists('mb_strtolower')
            ? mb_strtolower($name, 'UTF-8')
            : strtolower($name);
    }

    private static function normalizeSteamId(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $steamId = trim((string)$value);
        return preg_match('/^7656119[0-9]{10}$/D', $steamId) === 1 ? $steamId : null;
    }
}
