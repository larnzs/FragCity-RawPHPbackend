<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/loader.php';
require_once __DIR__ . '/WeaponPaintsApiClient.php';

/**
 * Typed boundary for WeaponPaints data.
 *
 * Local development may use a local MariaDB connection. Production can select
 * transport=api so the shared host never receives credentials for the Linux DB.
 * No method accepts SQL supplied by callers.
 */
final class WeaponPaintsRepository
{
    private ?PDO $pdo = null;
    private ?WeaponPaintsApiClient $api = null;

    public function __construct()
    {
        $settings = fragcity_settings('files.database.weaponpaints_plugin', []);
        $transport = is_array($settings) ? (string)($settings['transport'] ?? 'local') : 'local';

        if ($transport === 'api') {
            $this->api = new WeaponPaintsApiClient();
            return;
        }

        if ($transport !== 'local') {
            throw new RuntimeException('Unsupported WeaponPaints transport.');
        }

        $profile = is_array($settings) ? (string)($settings['database_profile'] ?? 'weaponpaints') : 'weaponpaints';
        $this->pdo = fragcity_create_pdo($profile);
        if (!$this->pdo instanceof PDO) {
            throw new RuntimeException('Local WeaponPaints database is not configured.');
        }
    }

    private function run(string $sql, array $params = []): void
    {
        if (!$this->pdo instanceof PDO) {
            throw new LogicException('Local database connection is unavailable.');
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    private function transaction(callable $callback): void
    {
        if (!$this->pdo instanceof PDO) {
            throw new LogicException('Local database connection is unavailable.');
        }
        $this->pdo->beginTransaction();
        try {
            $callback();
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function callApi(string $action, array $data = []): array
    {
        if (!$this->api instanceof WeaponPaintsApiClient) {
            throw new LogicException('API transport is unavailable.');
        }
        return $this->api->request(array_merge(['action' => $action], $data));
    }

    public function getPlayerSelection(string $steamid): array
    {
        if ($this->api instanceof WeaponPaintsApiClient) {
            $data = $this->callApi('get_selection', ['steamid' => $steamid]);
            return [
                'skins' => is_array($data['skins'] ?? null) ? $data['skins'] : [],
                'knife' => is_array($data['knife'] ?? null) ? $data['knife'] : [],
                'agents' => is_array($data['agents'] ?? null) ? $data['agents'] : [],
                'music' => is_array($data['music'] ?? null) ? $data['music'] : [],
                'pins' => is_array($data['pins'] ?? null) ? $data['pins'] : [],
            ];
        }

        if (!$this->pdo instanceof PDO) {
            throw new LogicException('Database connection is unavailable.');
        }

        $queries = [
            'skins' => 'SELECT weapon_defindex, weapon_paint_id, weapon_wear, weapon_seed, weapon_team,
                               weapon_nametag, weapon_stattrak, weapon_sticker_0, weapon_sticker_1,
                               weapon_sticker_2, weapon_sticker_3, weapon_sticker_4, weapon_keychain
                        FROM wp_player_skins WHERE steamid = :steamid',
            'knife' => 'SELECT knife, weapon_team FROM wp_player_knife WHERE steamid = :steamid LIMIT 1',
            'agents' => 'SELECT agent_t, agent_ct FROM wp_player_agents WHERE steamid = :steamid LIMIT 1',
            'music' => 'SELECT weapon_team, music_id FROM wp_player_music WHERE steamid = :steamid',
            'pins' => 'SELECT weapon_team, id FROM wp_player_pins WHERE steamid = :steamid',
        ];

        $result = [];
        foreach ($queries as $key => $sql) {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([':steamid' => $steamid]);
            $result[$key] = $statement->fetchAll();
        }
        return $result;
    }

    public function saveGlove(string $steamid, array $teams, int $defindex, int $paint): void
    {
        if ($this->api instanceof WeaponPaintsApiClient) {
            $this->callApi('save_glove', [
                'steamid' => $steamid, 'teams' => array_values($teams),
                'defindex' => $defindex, 'paint_id' => $paint,
            ]);
            return;
        }

        $this->transaction(function () use ($steamid, $teams, $defindex, $paint): void {
            foreach ($teams as $team) {
                $this->run(
                    'INSERT INTO wp_player_skins
                        (steamid, weapon_team, weapon_defindex, weapon_paint_id, weapon_wear, weapon_seed)
                     VALUES (:steamid, :team, :defindex, :paint, 0.001, 0)
                     ON DUPLICATE KEY UPDATE weapon_paint_id = :paint_update',
                    [
                        ':steamid' => $steamid, ':team' => $team, ':defindex' => $defindex,
                        ':paint' => $paint, ':paint_update' => $paint,
                    ]
                );
                $this->run(
                    'INSERT INTO wp_player_gloves (steamid, weapon_team, weapon_defindex)
                     VALUES (:steamid, :team, :defindex)
                     ON DUPLICATE KEY UPDATE weapon_defindex = :defindex_update',
                    [
                        ':steamid' => $steamid, ':team' => $team, ':defindex' => $defindex,
                        ':defindex_update' => $defindex,
                    ]
                );
            }
        });
    }

    public function saveAgent(string $steamid, array $teams, string $agent): void
    {
        if ($this->api instanceof WeaponPaintsApiClient) {
            $this->callApi('save_agent', [
                'steamid' => $steamid, 'teams' => array_values($teams), 'agent_id' => $agent,
            ]);
            return;
        }

        $this->transaction(function () use ($steamid, $teams, $agent): void {
            foreach ($teams as $team) {
                if ((int)$team === 2) {
                    $this->run(
                        'INSERT INTO wp_player_agents (steamid, agent_t) VALUES (:steamid, :agent)
                         ON DUPLICATE KEY UPDATE agent_t = :agent_update',
                        [':steamid' => $steamid, ':agent' => $agent, ':agent_update' => $agent]
                    );
                } else {
                    $this->run(
                        'INSERT INTO wp_player_agents (steamid, agent_ct) VALUES (:steamid, :agent)
                         ON DUPLICATE KEY UPDATE agent_ct = :agent_update',
                        [':steamid' => $steamid, ':agent' => $agent, ':agent_update' => $agent]
                    );
                }
            }
        });
    }

    public function saveMusic(string $steamid, array $teams, int $musicId): void
    {
        if ($this->api instanceof WeaponPaintsApiClient) {
            $this->callApi('save_music', [
                'steamid' => $steamid, 'teams' => array_values($teams), 'music_id' => $musicId,
            ]);
            return;
        }

        $this->transaction(function () use ($steamid, $teams, $musicId): void {
            foreach ($teams as $team) {
                $this->run(
                    'INSERT INTO wp_player_music (steamid, weapon_team, music_id)
                     VALUES (:steamid, :team, :music_id)
                     ON DUPLICATE KEY UPDATE music_id = :music_update',
                    [
                        ':steamid' => $steamid, ':team' => $team, ':music_id' => $musicId,
                        ':music_update' => $musicId,
                    ]
                );
            }
        });
    }

    public function savePin(string $steamid, array $teams, int $pinId): void
    {
        if ($this->api instanceof WeaponPaintsApiClient) {
            $this->callApi('save_pin', [
                'steamid' => $steamid, 'teams' => array_values($teams), 'pin_id' => $pinId,
            ]);
            return;
        }

        $this->transaction(function () use ($steamid, $teams, $pinId): void {
            foreach ($teams as $team) {
                $this->run(
                    'INSERT INTO wp_player_pins (steamid, weapon_team, id)
                     VALUES (:steamid, :team, :pin_id)
                     ON DUPLICATE KEY UPDATE id = :pin_update',
                    [
                        ':steamid' => $steamid, ':team' => $team, ':pin_id' => $pinId,
                        ':pin_update' => $pinId,
                    ]
                );
            }
        });
    }

    public function saveKnife(string $steamid, string $knife): void
    {
        if ($this->api instanceof WeaponPaintsApiClient) {
            $this->callApi('save_knife', [
                'steamid' => $steamid, 'teams' => [2, 3], 'knife' => $knife,
            ]);
            return;
        }

        $this->transaction(function () use ($steamid, $knife): void {
            foreach ([2, 3] as $team) {
                $this->run(
                    'INSERT INTO wp_player_knife (steamid, knife, weapon_team)
                     VALUES (:steamid, :knife, :team)
                     ON DUPLICATE KEY UPDATE knife = :knife_update',
                    [
                        ':steamid' => $steamid, ':knife' => $knife, ':team' => $team,
                        ':knife_update' => $knife,
                    ]
                );
            }
        });
    }

    /**
     * $stickers use the existing seven-part serialized plugin value and
     * $keychain uses the existing five-part serialized plugin value.
     */
    public function saveSkin(
        string $steamid,
        array $teams,
        int $defindex,
        int $paint,
        float $wear,
        int $seed,
        ?string $nametag,
        int $stattrak,
        array $stickers,
        string $keychain
    ): void {
        $stickerValues = array_values($stickers);
        if (count($stickerValues) !== 5) {
            throw new InvalidArgumentException('Expected five sticker slots.');
        }

        if ($this->api instanceof WeaponPaintsApiClient) {
            $stickerPayload = [];
            foreach ($stickerValues as $serialized) {
                $parts = explode(';', (string)$serialized);
                if (count($parts) < 7) {
                    throw new InvalidArgumentException('Invalid serialized sticker slot.');
                }
                $stickerPayload[] = [
                    'id' => (int)$parts[0],
                    'x' => (float)$parts[2],
                    'y' => (float)$parts[3],
                    'wear' => (float)$parts[4],
                    'scale' => (float)$parts[5],
                    'rotate' => (float)$parts[6],
                ];
            }

            $keychainParts = explode(';', $keychain);
            if (count($keychainParts) < 5) {
                throw new InvalidArgumentException('Invalid serialized keychain.');
            }

            $this->callApi('save_skin', [
                'steamid' => $steamid,
                'teams' => array_values($teams),
                'defindex' => $defindex,
                'paint_id' => $paint,
                'wear' => $wear,
                'seed' => $seed,
                'nametag' => $nametag ?? '',
                'stattrak' => $stattrak === 1,
                'stickers' => $stickerPayload,
                'keychain' => [
                    'id' => (int)$keychainParts[0],
                    'x' => (float)$keychainParts[1],
                    'y' => (float)$keychainParts[2],
                    'z' => (float)$keychainParts[3],
                    'seed' => (int)$keychainParts[4],
                ],
            ]);
            return;
        }

        $this->transaction(function () use (
            $steamid, $teams, $defindex, $paint, $wear, $seed, $nametag,
            $stattrak, $stickerValues, $keychain
        ): void {
            foreach ($teams as $team) {
                $this->run(
                    'INSERT INTO wp_player_skins
                        (steamid, weapon_team, weapon_defindex, weapon_paint_id, weapon_wear, weapon_seed,
                         weapon_nametag, weapon_stattrak, weapon_sticker_0, weapon_sticker_1,
                         weapon_sticker_2, weapon_sticker_3, weapon_sticker_4, weapon_keychain)
                     VALUES
                        (:steamid, :team, :defindex, :paint, :wear, :seed, :nametag, :stattrak,
                         :sticker0, :sticker1, :sticker2, :sticker3, :sticker4, :keychain)
                     ON DUPLICATE KEY UPDATE
                        weapon_paint_id = :paint_update,
                        weapon_wear = :wear_update,
                        weapon_seed = :seed_update,
                        weapon_nametag = :nametag_update,
                        weapon_stattrak = :stattrak_update,
                        weapon_sticker_0 = :sticker0_update,
                        weapon_sticker_1 = :sticker1_update,
                        weapon_sticker_2 = :sticker2_update,
                        weapon_sticker_3 = :sticker3_update,
                        weapon_sticker_4 = :sticker4_update,
                        weapon_keychain = :keychain_update',
                    [
                        ':steamid' => $steamid, ':team' => $team, ':defindex' => $defindex,
                        ':paint' => $paint, ':wear' => $wear, ':seed' => $seed,
                        ':nametag' => $nametag, ':stattrak' => $stattrak,
                        ':sticker0' => $stickerValues[0], ':sticker1' => $stickerValues[1],
                        ':sticker2' => $stickerValues[2], ':sticker3' => $stickerValues[3],
                        ':sticker4' => $stickerValues[4], ':keychain' => $keychain,
                        ':paint_update' => $paint, ':wear_update' => $wear, ':seed_update' => $seed,
                        ':nametag_update' => $nametag, ':stattrak_update' => $stattrak,
                        ':sticker0_update' => $stickerValues[0], ':sticker1_update' => $stickerValues[1],
                        ':sticker2_update' => $stickerValues[2], ':sticker3_update' => $stickerValues[3],
                        ':sticker4_update' => $stickerValues[4], ':keychain_update' => $keychain,
                    ]
                );
            }
        });
    }
}
