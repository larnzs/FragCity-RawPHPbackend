<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings/loader.php';
require_once __DIR__ . '/FragCityLinuxApiClient.php';

/**
 * Website-side WeaponPaints boundary.
 *
 * The website must never connect directly to the WeaponPaints database.
 * All reads and writes go through the signed HTTPS Linux API.
 */
final class WeaponPaintsRepository
{
    private FragCityLinuxApiClient $api;

    public function __construct()
    {
        $files = fragcity_settings('files', []);
        $settings = is_array($files) ? ($files['database.weaponpaints_plugin'] ?? []) : [];
        $transport = is_array($settings) ? (string)($settings['transport'] ?? 'api') : 'api';

        if ($transport !== 'api') {
            throw new RuntimeException('WeaponPaints is API-only; direct database transport is disabled.');
        }

        $this->api = new FragCityLinuxApiClient();
    }

    private function callApi(string $action, array $payload = []): array
    {
        return $this->api->request(array_merge(['action' => $action], $payload));
    }

    public function getPlayerSelection(string $steamid): array
    {
        $data = $this->callApi('get_selection', ['steamid' => $steamid]);
        return [
            'skins' => is_array($data['skins'] ?? null) ? $data['skins'] : [],
            'skin_inventory' => is_array($data['skin_inventory'] ?? null) ? $data['skin_inventory'] : [],
            'knife' => is_array($data['knife'] ?? null) ? $data['knife'] : [],
            'agents' => is_array($data['agents'] ?? null) ? $data['agents'] : [],
            'music' => is_array($data['music'] ?? null) ? $data['music'] : [],
            'pins' => is_array($data['pins'] ?? null) ? $data['pins'] : [],
        ];
    }

    public function saveGlove(string $steamid, array $teams, int $defindex, int $paint): void
    {
        $this->callApi('save_glove', [
            'steamid' => $steamid,
            'teams' => array_values($teams),
            'defindex' => $defindex,
            'paint_id' => $paint,
        ]);
    }

    public function saveAgent(string $steamid, array $teams, string $agent): void
    {
        $this->callApi('save_agent', [
            'steamid' => $steamid,
            'teams' => array_values($teams),
            'agent_id' => $agent,
        ]);
    }

    public function saveMusic(string $steamid, array $teams, int $musicId): void
    {
        $this->callApi('save_music', [
            'steamid' => $steamid,
            'teams' => array_values($teams),
            'music_id' => $musicId,
        ]);
    }

    public function savePin(string $steamid, array $teams, int $pinId): void
    {
        $this->callApi('save_pin', [
            'steamid' => $steamid,
            'teams' => array_values($teams),
            'pin_id' => $pinId,
        ]);
    }

    public function saveKnife(string $steamid, string $knife): void
    {
        $this->callApi('save_knife', [
            'steamid' => $steamid,
            'teams' => [2, 3],
            'knife' => $knife,
        ]);
    }

    /**
     * Delete one saved inventory record. The Linux API also clears the
     * active plugin row only when it references this exact same paint.
     */
    public function deleteSkinInventory(
        string $steamid,
        int $team,
        int $defindex,
        int $paint
    ): bool {
        if (!in_array($team, [2, 3], true)) {
            throw new InvalidArgumentException('Invalid inventory team.');
        }
        if ($defindex < 1 || $defindex > 10000 || $paint < 0 || $paint > 100000) {
            throw new InvalidArgumentException('Invalid inventory skin key.');
        }

        $data = $this->callApi('delete_skin', [
            'steamid' => $steamid,
            'teams' => [$team],
            'defindex' => $defindex,
            'paint_id' => $paint,
        ]);

        return ($data['deleted'] ?? false) === true;
    }

    /**
     * Clear only this player's saved firearm-skin inventory and active firearm
     * skin rows. Knives, gloves, agents, music kits and pins are not touched.
     *
     * @return array{deleted_inventory:int, deleted_active:int}
     */
    public function clearSkinInventory(string $steamid): array
    {
        if (!preg_match('/^[0-9]{17}$/D', $steamid)) {
            throw new InvalidArgumentException('Invalid inventory player identifier.');
        }

        $data = $this->callApi('clear_skin_inventory', [
            'steamid' => $steamid,
            'teams' => [2, 3],
        ]);

        return [
            'deleted_inventory' => (int)($data['deleted_inventory'] ?? 0),
            'deleted_active' => (int)($data['deleted_active'] ?? 0),
        ];
    }

    /**
     * Preserve the plugin's serialized values at the page boundary, and convert
     * them to structured, validated JSON before sending them to Linux API.
     *
     * Sticker serialization: id;0;x;y;wear;scale;rotate
     * Keychain serialization: id;x;y;z;seed
     *
     * @param array<int, string> $stickers
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
        $stickers = array_values($stickers);
        if (count($stickers) !== 5) {
            throw new InvalidArgumentException('Exactly five sticker slots are required.');
        }

        $stickerPayload = [];
        foreach ($stickers as $serialized) {
            $parts = explode(';', (string)$serialized);
            if (count($parts) < 7) {
                throw new InvalidArgumentException('Invalid serialized sticker value.');
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
            throw new InvalidArgumentException('Invalid serialized keychain value.');
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
    }
}
