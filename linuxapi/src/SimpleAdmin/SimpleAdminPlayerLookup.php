<?php
declare(strict_types=1);

/**
 * Read-only exact-name resolver for SimpleAdmin player history.
 * Returns SteamID64 only; it never selects the stored IP column.
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

        $namesByHash = [];
        foreach ($input as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Player names must be strings.');
            }
            $name = trim($value);
            if ($name === '') {
                continue;
            }
            if (!preg_match('//u', $name)
                || preg_match('/[\x00-\x1F\x7F]/', $name)
                || mb_strlen($name, 'UTF-8') > self::MAX_NAME_LENGTH) {
                throw new InvalidArgumentException('Invalid player name.');
            }
            $namesByHash[hash('sha256', $name)] = $name;
        }

        $names = array_values($namesByHash);
        if ($names === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($names as $idx => $name) {
            $placeholder = ':name' . $idx;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $name;
        }

        // One candidate means unique identity; two means ambiguous.
        $sql = '
            SELECT candidate_name AS player_name, steam_id
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
            ORDER BY player_name ASC, candidate_rank ASC
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $candidates = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $candidateName = is_string($row['player_name'] ?? null) ? $row['player_name'] : '';
            $steamId = is_string($row['steam_id'] ?? null) ? trim($row['steam_id']) : '';
            $nameHash = hash('sha256', $candidateName);

            // The DB's collation may be case-insensitive; accept only byte-exact names.
            if (!isset($namesByHash[$nameHash])
                || $namesByHash[$nameHash] !== $candidateName
                || preg_match('/^7656119[0-9]{10}$/D', $steamId) !== 1) {
                continue;
            }
            $candidates[$nameHash][$steamId] = true;
        }

        $matches = [];
        foreach ($names as $name) {
            $steamIds = array_keys($candidates[hash('sha256', $name)] ?? []);
            if (count($steamIds) === 1) {
                $matches[] = ['name' => $name, 'steam_id' => $steamIds[0], 'status' => 'resolved'];
            } elseif (count($steamIds) > 1) {
                $matches[] = ['name' => $name, 'steam_id' => null, 'status' => 'ambiguous'];
            } else {
                $matches[] = ['name' => $name, 'steam_id' => null, 'status' => 'not_found'];
            }
        }

        return $matches;
    }
}
