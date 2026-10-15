<?php
// weaponpaints.php
// FragCity Weapon Customization Panel
// Logic-preserving refactor:
// - Session/Auth preserved
// - Database logic preserved
// - POST save logic preserved
// - Team selection preserved
// - Pagination preserved
// - Modal/Tab hooks preserved
// - CSS/JS moved to external files

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Canonical route
|--------------------------------------------------------------------------
| This file is rendered through index.php?page=skins. Direct requests are
| sent back to the canonical FragCity route instead of rendering a second
| standalone document.
*/
if (!defined('FRAGCITY_INDEX_RENDER')) {
    header('Location: /index.php?page=skins', true, 303);
    exit;
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['user_logged_in'])) {
    echo '<div class="wp-auth-state">لطفاً از سایت اصلی وارد شوید.</div>';
    exit;
}

$steamid = (string)($_SESSION['steamid'] ?? '');

if ($steamid === '') {
    echo '<div class="wp-auth-state">اطلاعات کاربر نامعتبر.</div>';
    exit;
}

session_write_close();

/*
|--------------------------------------------------------------------------
| Dependencies
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../class/config.php';
require_once __DIR__ . '/../database/kyonxcbt_weaponpaints_plugin.php';
require_once __DIR__ . '/../class/utils.php';

if (!defined('IMAGE_DEFAULT_GLOVE_T')) {
    define('IMAGE_DEFAULT_GLOVE_T', '');
}

if (!defined('IMAGE_DEFAULT_GLOVE_CT')) {
    define('IMAGE_DEFAULT_GLOVE_CT', '');
}

$db = new RemoteDataBase();

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function esc($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function normalizeTeamSelection(string $team): string
{
    return in_array($team, ['2', '3', 'both'], true)
        ? $team
        : '2';
}

function normalizeTabSelection(string $tab): string
{
    $allowedTabs = [
        'tab-knives',
        'tab-skins',
        'tab-gloves',
        'tab-agents',
        'tab-music',
        'tab-pins',
        'tab-inventory'
    ];

    return in_array($tab, $allowedTabs, true)
        ? $tab
        : 'tab-knives';
}

function currentScriptPath(): string
{
    return '/index.php?page=skins';
}

function redirectToSelf(
    string $team,
    string $tab,
    ?int $skinPage = null,
    ?int $pinPage = null
): never {
    $team = normalizeTeamSelection($team);
    $tab = normalizeTabSelection($tab);

    $location = currentScriptPath()
        . '&team=' . rawurlencode($team)
        . '&tab=' . rawurlencode($tab);

    if ($skinPage !== null) {
        $location .= '&skin_page=' . max(1, $skinPage);
    }

    if ($pinPage !== null) {
        $location .= '&pin_page=' . max(1, $pinPage);
    }

    header('Location: ' . $location, true, 303);
    exit;
}

function buildSkinSelectionMaps(array $rows): array
{
    $selectedSkins = [];
    $skinDetails = [];
    $selectedGloves = [
        2 => [],
        3 => []
    ];

    $gloveDefindexes = [
        0,
        4725,
        5027,
        5030,
        5031,
        5032,
        5033,
        5034,
        5035
    ];

    foreach ($rows as $row) {
        $def = isset($row['weapon_defindex'])
            ? (int)$row['weapon_defindex']
            : 0;

        $team = isset($row['weapon_team'])
            ? (int)$row['weapon_team']
            : 0;

        if ($def === 0 || $team === 0) {
            continue;
        }

        $skinDetails[$def][$team] = $row;

        if (in_array($def, $gloveDefindexes, true)) {
            $selectedGloves[$team][$def] =
                $row['weapon_paint_id'] ?? null;
        } else {
            $selectedSkins[$def] = $row;
        }
    }

    return [
        $selectedSkins,
        $skinDetails,
        $selectedGloves
    ];
}

function resolveLocalAssetUrl(
    ?string $relative,
    array $candidateRoots = []
): string {
    $relative = trim((string)$relative);

    if ($relative === '') {
        return '';
    }

    // Remote/data URLs are already valid and must not be rewritten.
    if (preg_match('#^(https?:)?//#i', $relative) || str_starts_with($relative, 'data:')) {
        return $relative;
    }

    $relative = ltrim(
        str_replace('\\', '/', $relative),
        '/'
    );

    $documentRoot = rtrim(
        (string)($_SERVER['DOCUMENT_ROOT'] ?? ''),
        '/\\'
    );

    $normalized = preg_replace('#/+#', '/', $relative) ?: $relative;

    $pathSegments = array_map(
        'rawurlencode',
        explode('/', $normalized)
    );

    $encodedRelative = implode('/', $pathSegments);

    // First test the path exactly as supplied. This is important when the
    // dataset already contains a root-relative path such as assets/img/....
    if ($documentRoot !== '' && is_file($documentRoot . '/' . $normalized)) {
        return '/' . $encodedRelative;
    }

    $knownRootPrefixes = [
        'assets/',
        'img/',
        'skins/',
        'weapons/',
        'knives/',
        'gloves/'
    ];

    $roots = array_merge([
        'assets/img/skins',
        'assets/img/agents',
        'assets/img/weapons',
        'assets/img/knives',
        'assets/img/gloves',
        'assets/images/skins',
        'assets/images/agents',
        'assets/images/weapons',
        'assets/images/knives',
        'assets/images/gloves',
        'assets/agents',
        'assets/skins',
        'assets/weapons',
        'assets/knives',
        'assets/gloves',
        'img/skins',
        'img/agents',
        'img/weapons',
        'img/knives',
        'img/gloves',
        'skins',
        'weapons',
        'knives',
        'gloves'
    ], $candidateRoots);

    // Avoid generating nonsense paths such as assets/img/skins/assets/...
    // when the source already includes one of the known root prefixes.
    $alreadyRooted = false;

    foreach ($knownRootPrefixes as $prefix) {
        if (str_starts_with($normalized, $prefix)) {
            $alreadyRooted = true;
            break;
        }
    }

    if (!$alreadyRooted) {
        foreach ($roots as $root) {
            $root = trim(
                str_replace('\\', '/', (string)$root),
                '/'
            );

            if ($root === '') {
                continue;
            }

            $candidate = $root . '/' . $normalized;

            if (
                $documentRoot !== '' &&
                is_file($documentRoot . '/' . $candidate)
            ) {
                return '/' . implode(
                    '/',
                    array_map(
                        'rawurlencode',
                        explode('/', $candidate)
                    )
                );
            }
        }
    }

    // Keep the old behavior as a safe fallback for unresolved assets.
    return '/' . $encodedRelative;
}

function resolveAgentImageUrl(string $image): string
{
    return resolveLocalAssetUrl(
        $image,
        [
            'assets/img/skins',
            'assets/img/agents',
            'assets/agents',
            'assets/images/agents',
            'img/skins',
            'img/agents',
            'skins'
        ]
    );
}

function normalizeAgentsDataset(array $agentsData): array
{
    foreach ($agentsData as $teamKey => $agents) {
        if (!is_array($agents)) {
            continue;
        }

        foreach ($agents as $id => $agent) {
            if (!is_array($agent)) {
                continue;
            }

            $name = trim(
                (string)(
                    $agent['name']
                    ?? $agent['agent_name']
                    ?? ''
                )
            );

            if ($name !== '') {
                $agent['name'] = $name;
            }

            $image = (string)(
                $agent['image_url']
                ?? $agent['image']
                ?? ''
            );

            $agent['image_url'] =
                resolveAgentImageUrl($image);

            $agents[$id] = $agent;
        }

        $agentsData[$teamKey] = $agents;
    }

    return $agentsData;
}

function buildSkinPageUrl(
    string $teamParam,
    int $page
): string {
    $teamParam = normalizeTeamSelection($teamParam);
    $page = max(1, $page);

    return currentScriptPath()
        . '&team=' . rawurlencode($teamParam)
        . '&tab=tab-skins'
        . '&skin_page=' . $page;
}

function buildPinPageUrl(
    string $teamParam,
    int $page
): string {
    $teamParam = normalizeTeamSelection($teamParam);
    $page = max(1, $page);

    return currentScriptPath()
        . '&team=' . rawurlencode($teamParam)
        . '&tab=tab-pins'
        . '&pin_page=' . $page;
}

function imageMarkup(
    string $src,
    string $alt = '',
    string $class = 'wp-card-image'
): string {
    $resolvedSrc = resolveLocalAssetUrl($src);

    if ($resolvedSrc === '') {
        return '<div class="wp-image-placeholder">No Image</div>';
    }

    return sprintf(
        '<img src="%s" alt="%s" class="%s" loading="lazy" decoding="async" data-wp-image>'
        . '<div class="wp-image-placeholder is-hidden">No Image</div>',
        esc($resolvedSrc),
        esc($alt),
        esc($class)
    );
}

function renderPagination(
    string $type,
    string $teamParam,
    int $currentPage,
    int $totalPages
): string {
    $currentPage = max(1, min($currentPage, $totalPages));
    $isSkin = ($type === 'skins');

    $builder = $isSkin
        ? 'buildSkinPageUrl'
        : 'buildPinPageUrl';

    $navigateFunction = $isSkin
        ? 'navigateSkinPage'
        : 'navigatePinPage';

    $previousPage = max(1, $currentPage - 1);
    $nextPage = min($totalPages, $currentPage + 1);

    $html = '<div class="wp-pagination">';

    $html .= sprintf(
        '<a href="%s" class="wp-page-btn wp-page-btn--nav" data-wp-page="%d" data-wp-navigation="%s" aria-label="Previous page">'
        . '<i class="fas fa-angle-right"></i>'
        . '</a>',
        esc($builder($teamParam, $previousPage)),
        $previousPage,
        $navigateFunction,
        $previousPage
    );

    for ($page = 1; $page <= $totalPages; $page++) {
        $activeClass = ($page === $currentPage)
            ? ' is-active'
            : '';

        $html .= sprintf(
            '<a href="%s" class="wp-page-btn%s" data-wp-page="%d" data-wp-navigation="%s">%d</a>',
            esc($builder($teamParam, $page)),
            $activeClass,
            $page,
            $navigateFunction,
            $page,
            $page
        );
    }

    $html .= sprintf(
        '<a href="%s" class="wp-page-btn wp-page-btn--nav" data-wp-page="%d" data-wp-navigation="%s" aria-label="Next page">'
        . '<i class="fas fa-angle-left"></i>'
        . '</a>',
        esc($builder($teamParam, $nextPage)),
        $nextPage,
        $navigateFunction,
        $nextPage
    );

    $html .= '</div>';

    return $html;
}

/*
|--------------------------------------------------------------------------
| Load datasets
|--------------------------------------------------------------------------
*/

$weapons = UtilsClass::getWeaponsFromArray();
$skins = UtilsClass::skinsFromJson();
$knifes = UtilsClass::getKnifeTypes();
$allGloves = UtilsClass::getGlovesAllPaints();
$agentsData = UtilsClass::getAgents();
$musicData = UtilsClass::getMusic();
$pinsData = UtilsClass::getPins();
$stickersData = UtilsClass::getStickers();
$keychainsData = UtilsClass::getKeychains();

$agentsData = normalizeAgentsDataset($agentsData);

/*
|--------------------------------------------------------------------------
| Load player selections
|--------------------------------------------------------------------------
*/

$skinRows = $db->select(
    "SELECT
        weapon_defindex,
        weapon_paint_id,
        weapon_wear,
        weapon_seed,
        weapon_team,
        weapon_nametag,
        weapon_stattrak,
        weapon_sticker_0,
        weapon_sticker_1,
        weapon_sticker_2,
        weapon_sticker_3,
        weapon_sticker_4,
        weapon_keychain
     FROM wp_player_skins
     WHERE steamid = :steamid",
    [
        ':steamid' => $steamid
    ]
);

[
    $selectedSkins,
    $skinDetails,
    $selectedGloves
] = buildSkinSelectionMaps($skinRows);

$selectedKnifeRows = $db->select(
    "SELECT knife, weapon_team
     FROM wp_player_knife
     WHERE steamid = :steamid
     LIMIT 1",
    [
        ':steamid' => $steamid
    ]
);

$selectedKnife = $selectedKnifeRows[0] ?? [];

$selectedAgents = $db->select(
    "SELECT agent_t, agent_ct
     FROM wp_player_agents
     WHERE steamid = :s
     LIMIT 1",
    [
        ':s' => $steamid
    ]
);

$selectedAgentT = $selectedAgents[0]['agent_t'] ?? '';
$selectedAgentCT = $selectedAgents[0]['agent_ct'] ?? '';

$selectedMusic = $db->select(
    "SELECT weapon_team, music_id
     FROM wp_player_music
     WHERE steamid = :s",
    [
        ':s' => $steamid
    ]
);

$selectedMusicIds = [
    2 => 0,
    3 => 0
];

foreach ($selectedMusic as $row) {
    $teamKey = (int)($row['weapon_team'] ?? 0);

    if ($teamKey === 2 || $teamKey === 3) {
        $selectedMusicIds[$teamKey] =
            (int)($row['music_id'] ?? 0);
    }
}

$selectedPins = $db->select(
    "SELECT weapon_team, id
     FROM wp_player_pins
     WHERE steamid = :s",
    [
        ':s' => $steamid
    ]
);

$selectedPinIds = [
    2 => 0,
    3 => 0
];

foreach ($selectedPins as $row) {
    $teamKey = (int)($row['weapon_team'] ?? 0);

    if ($teamKey === 2 || $teamKey === 3) {
        $selectedPinIds[$teamKey] =
            (int)($row['id'] ?? 0);
    }
}

/*
|--------------------------------------------------------------------------
| Request state
|--------------------------------------------------------------------------
*/

$teamParam = normalizeTeamSelection(
    (string)($_GET['team'] ?? '2')
);

$team = $teamParam === 'both'
    ? 2
    : (int)$teamParam;

$currentTab = normalizeTabSelection(
    (string)($_GET['tab'] ?? 'tab-knives')
);

$skinPage = max(
    1,
    (int)($_POST['skin_page'] ?? $_GET['skin_page'] ?? 1)
);

$pinPage = max(
    1,
    (int)($_POST['pin_page'] ?? $_GET['pin_page'] ?? 1)
);

/*
|--------------------------------------------------------------------------
| Pins pagination
|--------------------------------------------------------------------------
*/

$pinPerPage = 24;

$pinItems = array_values($pinsData);

$pinTotalItems = count($pinItems);

$pinTotalPages = max(
    1,
    (int)ceil($pinTotalItems / $pinPerPage)
);

$pinPage = min(
    $pinPage,
    $pinTotalPages
);

$pinOffset = (
    $pinPage - 1
) * $pinPerPage;

$pinPageItems = array_slice(
    $pinItems,
    $pinOffset,
    $pinPerPage
);

$pinStartIndex =
    $pinTotalItems > 0
        ? $pinOffset + 1
        : 0;

$pinEndIndex = min(
    $pinOffset + $pinPerPage,
    $pinTotalItems
);

/*
|--------------------------------------------------------------------------
| POST SAVE LOGIC
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $activeTab = (string)(
        $_POST['active_tab']
        ?? 'tab-knives'
    );

    $save_team = normalizeTeamSelection(
        (string)(
            $_POST['team']
            ?? '2'
        )
    );

    $teams = $save_team === 'both'
        ? [2, 3]
        : [(int)$save_team];

    /*
    |--------------------------------------------------------------------------
    | Gloves
    |--------------------------------------------------------------------------
    */

    if (
        isset(
            $_POST['glove_defindex'],
            $_POST['glove_paint']
        )
    ) {
        $def = (int)$_POST['glove_defindex'];
        $paint = (int)$_POST['glove_paint'];

        foreach ($teams as $t) {

            $db->query(
                "INSERT INTO wp_player_skins
                (
                    steamid,
                    weapon_team,
                    weapon_defindex,
                    weapon_paint_id,
                    weapon_wear,
                    weapon_seed
                )
                VALUES
                (
                    :s,
                    :t,
                    :d,
                    :p,
                    0.001,
                    0
                )
                ON DUPLICATE KEY UPDATE
                    weapon_paint_id = :p2",
                [
                    ':s' => $steamid,
                    ':t' => $t,
                    ':d' => $def,
                    ':p' => $paint,
                    ':p2' => $paint
                ]
            );

            $db->query(
                "INSERT INTO wp_player_gloves
                (
                    steamid,
                    weapon_team,
                    weapon_defindex
                )
                VALUES
                (
                    :s,
                    :t,
                    :d
                )
                ON DUPLICATE KEY UPDATE
                    weapon_defindex = :d2",
                [
                    ':s' => $steamid,
                    ':t' => $t,
                    ':d' => $def,
                    ':d2' => $def
                ]
            );
        }

        redirectToSelf(
            $save_team,
            $activeTab,
            $skinPage,
            $pinPage
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Agents
    |--------------------------------------------------------------------------
    */

    if (isset($_POST['agent_id'])) {

        $agent = (string)$_POST['agent_id'];

        if ($save_team === 'both') {

            $db->query(
                "INSERT INTO wp_player_agents
                (
                    steamid,
                    agent_t
                )
                VALUES
                (
                    :s,
                    :a
                )
                ON DUPLICATE KEY UPDATE
                    agent_t = :a2",
                [
                    ':s' => $steamid,
                    ':a' => $agent,
                    ':a2' => $agent
                ]
            );

            $db->query(
                "INSERT INTO wp_player_agents
                (
                    steamid,
                    agent_ct
                )
                VALUES
                (
                    :s,
                    :a
                )
                ON DUPLICATE KEY UPDATE
                    agent_ct = :a2",
                [
                    ':s' => $steamid,
                    ':a' => $agent,
                    ':a2' => $agent
                ]
            );

        } else {

            $t = (int)$save_team;

            if ($t === 2) {

                $db->query(
                    "INSERT INTO wp_player_agents
                    (
                        steamid,
                        agent_t
                    )
                    VALUES
                    (
                        :s,
                        :a
                    )
                    ON DUPLICATE KEY UPDATE
                        agent_t = :a2",
                    [
                        ':s' => $steamid,
                        ':a' => $agent,
                        ':a2' => $agent
                    ]
                );

            } else {

                $db->query(
                    "INSERT INTO wp_player_agents
                    (
                        steamid,
                        agent_ct
                    )
                    VALUES
                    (
                        :s,
                        :a
                    )
                    ON DUPLICATE KEY UPDATE
                        agent_ct = :a2",
                    [
                        ':s' => $steamid,
                        ':a' => $agent,
                        ':a2' => $agent
                    ]
                );
            }
        }

        redirectToSelf(
            $save_team,
            $activeTab,
            $skinPage,
            $pinPage
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Music
    |--------------------------------------------------------------------------
    */

    if (isset($_POST['music_id'])) {

        $mid = (int)$_POST['music_id'];

        foreach ($teams as $t) {

            $db->query(
                "INSERT INTO wp_player_music
                (
                    steamid,
                    weapon_team,
                    music_id
                )
                VALUES
                (
                    :s,
                    :t,
                    :m
                )
                ON DUPLICATE KEY UPDATE
                    music_id = :m2",
                [
                    ':s' => $steamid,
                    ':t' => $t,
                    ':m' => $mid,
                    ':m2' => $mid
                ]
            );
        }

        redirectToSelf(
            $save_team,
            $activeTab,
            $skinPage,
            $pinPage
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pins
    |--------------------------------------------------------------------------
    */

    if (isset($_POST['pin_id'])) {

        $pid = (int)$_POST['pin_id'];

        foreach ($teams as $t) {

            $db->query(
                "INSERT INTO wp_player_pins
                (
                    steamid,
                    weapon_team,
                    id
                )
                VALUES
                (
                    :s,
                    :t,
                    :p
                )
                ON DUPLICATE KEY UPDATE
                    id = :p2",
                [
                    ':s' => $steamid,
                    ':t' => $t,
                    ':p' => $pid,
                    ':p2' => $pid
                ]
            );
        }

        redirectToSelf(
            $save_team,
            $activeTab,
            $skinPage,
            $pinPage
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Knives / Skins
    |--------------------------------------------------------------------------
    */

    if (isset($_POST['forma'])) {

        $parts = explode(
            '-',
            (string)$_POST['forma'],
            2
        );

        /*
        |--------------------------------------------------------------------------
        | Knife
        |--------------------------------------------------------------------------
        */

        if (($parts[0] ?? '') === 'knife') {

            $knifeKey = $parts[1] ?? '';

            if (
                isset(
                    $knifes[$knifeKey]['weapon_name']
                )
            ) {

                $knifeName =
                    $knifes[$knifeKey]['weapon_name'];

                $db->query(
                    "INSERT INTO wp_player_knife
                    (
                        steamid,
                        knife,
                        weapon_team
                    )
                    VALUES
                    (
                        :s,
                        :k,
                        2
                    )
                    ON DUPLICATE KEY UPDATE
                        knife = :k2",
                    [
                        ':s' => $steamid,
                        ':k' => $knifeName,
                        ':k2' => $knifeName
                    ]
                );

                $db->query(
                    "INSERT INTO wp_player_knife
                    (
                        steamid,
                        knife,
                        weapon_team
                    )
                    VALUES
                    (
                        :s,
                        :k,
                        3
                    )
                    ON DUPLICATE KEY UPDATE
                        knife = :k2",
                    [
                        ':s' => $steamid,
                        ':k' => $knifeName,
                        ':k2' => $knifeName
                    ]
                );
            }

            redirectToSelf(
                $save_team,
                $activeTab,
                $skinPage,
                $pinPage
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Weapon Skin
        |--------------------------------------------------------------------------
        */

        $def = (string)(
            $parts[0] ?? ''
        );

        $paint = (string)(
            $parts[1] ?? ''
        );

        $wear = max(
            0.0,
            min(
                1.0,
                (float)(
                    $_POST['wear']
                    ?? 0.001
                )
            )
        );

        $seed = (int)(
            $_POST['seed']
            ?? 0
        );

        $nametag = trim(
            (string)(
                $_POST['nametag']
                ?? ''
            )
        );

        $nametag =
            $nametag === ''
                ? null
                : mb_substr(
                    $nametag,
                    0,
                    20
                );

        $stattrak =
            isset($_POST['stattrak'])
                ? 1
                : 0;

        $stickers = [];

        for ($i = 0; $i < 5; $i++) {

            $s = $_POST['sticker'][$i]
                ?? [];

            $stickers[] = implode(
                ';',
                [
                    (int)(
                        $s['id']
                        ?? 0
                    ),
                    0,
                    (float)(
                        $s['x']
                        ?? 0
                    ),
                    (float)(
                        $s['y']
                        ?? 0
                    ),
                    (float)(
                        $s['wear']
                        ?? 0.001
                    ),
                    (float)(
                        $s['scale']
                        ?? 1
                    ),
                    (float)(
                        $s['rotate']
                        ?? 0
                    )
                ]
            );
        }

        $kc = $_POST['keychain']
            ?? [];

        $keychainStr = implode(
            ';',
            [
                (int)(
                    $kc['id']
                    ?? 0
                ),
                (float)(
                    $kc['x']
                    ?? 0
                ),
                (float)(
                    $kc['y']
                    ?? 0
                ),
                (float)(
                    $kc['z']
                    ?? 0
                ),
                (int)(
                    $kc['seed']
                    ?? 0
                )
            ]
        );

        if (
            isset(
                $skins[$def][$paint]
            )
        ) {

            foreach ($teams as $t) {

                $db->query(
                    "INSERT INTO wp_player_skins
                    (
                        steamid,
                        weapon_team,
                        weapon_defindex,
                        weapon_paint_id,
                        weapon_wear,
                        weapon_seed,
                        weapon_nametag,
                        weapon_stattrak,
                        weapon_sticker_0,
                        weapon_sticker_1,
                        weapon_sticker_2,
                        weapon_sticker_3,
                        weapon_sticker_4,
                        weapon_keychain
                    )
                    VALUES
                    (
                        :s,
                        :t,
                        :d,
                        :p,
                        :w,
                        :seed,
                        :nametag,
                        :stattrak,
                        :s0,
                        :s1,
                        :s2,
                        :s3,
                        :s4,
                        :keychain
                    )
                    ON DUPLICATE KEY UPDATE
                        weapon_paint_id = :p2,
                        weapon_wear = :w2,
                        weapon_seed = :seed2,
                        weapon_nametag = :nametag2,
                        weapon_stattrak = :stattrak2,
                        weapon_sticker_0 = :s02,
                        weapon_sticker_1 = :s12,
                        weapon_sticker_2 = :s22,
                        weapon_sticker_3 = :s32,
                        weapon_sticker_4 = :s42,
                        weapon_keychain = :keychain2",
                    [
                        ':s' => $steamid,
                        ':t' => $t,
                        ':d' => $def,
                        ':p' => $paint,
                        ':w' => $wear,
                        ':seed' => $seed,

                        ':nametag' => $nametag,
                        ':stattrak' => $stattrak,

                        ':s0' => $stickers[0],
                        ':s1' => $stickers[1],
                        ':s2' => $stickers[2],
                        ':s3' => $stickers[3],
                        ':s4' => $stickers[4],

                        ':keychain' => $keychainStr,

                        ':p2' => $paint,
                        ':w2' => $wear,
                        ':seed2' => $seed,
                        ':nametag2' => $nametag,
                        ':stattrak2' => $stattrak,

                        ':s02' => $stickers[0],
                        ':s12' => $stickers[1],
                        ':s22' => $stickers[2],
                        ':s32' => $stickers[3],
                        ':s42' => $stickers[4],

                        ':keychain2' => $keychainStr
                    ]
                );
            }
        }

        redirectToSelf(
            $save_team,
            $activeTab,
            $skinPage,
            $pinPage
        );
    }
}

/*
|--------------------------------------------------------------------------
| Skin pagination
|--------------------------------------------------------------------------
*/

$weaponsList = [];

foreach ($weapons as $def => $weapon) {
    $weaponsList[] = [
        'def' => $def,
        'weapon' => $weapon
    ];
}

$skinPerPage = 24;

$skinTotalItems =
    count($weaponsList);

$skinTotalPages = max(
    1,
    (int)ceil(
        $skinTotalItems
        / $skinPerPage
    )
);

$skinPage = min(
    $skinPage,
    $skinTotalPages
);

$skinOffset =
    ($skinPage - 1)
    * $skinPerPage;

$skinPageItems =
    array_slice(
        $weaponsList,
        $skinOffset,
        $skinPerPage
    );

$skinStartIndex =
    $skinTotalItems > 0
        ? $skinOffset + 1
        : 0;

$skinEndIndex =
    min(
        $skinOffset
        + $skinPerPage,
        $skinTotalItems
    );

/*
|--------------------------------------------------------------------------
| View state
|--------------------------------------------------------------------------
*/

$teamLabelMap = [
    '2' => 'T',
    '3' => 'CT',
    'both' => 'Both'
];

$currentTeamLabel =
    $teamLabelMap[$teamParam]
    ?? 'T';

$displayTeamList =
    $teamParam === 'both'
        ? [2, 3]
        : [$team];

$currentKnifeName =
    ($selectedKnife[0]['knife'] ?? '')
        ?: null;

$currentKnife = null;

if ($currentKnifeName) {
    foreach ($knifes as $knife) {
        if (
            $knife['weapon_name']
            === $currentKnifeName
        ) {
            $currentKnife = $knife;
            break;
        }
    }
}

$agentTeams =
    $teamParam === 'both'
        ? [
            't' => $agentsData['t'] ?? [],
            'ct' => $agentsData['ct'] ?? []
        ]
        : (
            $team === 2
                ? [
                    't' =>
                        $agentsData['t']
                        ?? []
                ]
                : [
                    'ct' =>
                        $agentsData['ct']
                        ?? []
                ]
        );

$musicTeams =
    $teamParam === 'both'
        ? [2, 3]
        : [$team];

$pinTeams =
    $teamParam === 'both'
        ? [2, 3]
        : [$team];

$jsonFlags = JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
    | JSON_INVALID_UTF8_SUBSTITUTE;

?>

<div class="wp-app">

    <!-- =========================================================
         TOP HEADER
    ========================================================== -->

    <header class="wp-header">

        <div class="wp-header-main">

            <!-- Search -->

            <div class="wp-search-wrap">

                <i class="fas fa-magnifying-glass"></i>

                <input
                    type="search"
                    id="skinSearchGlobal"
                    class="wp-search"
                    placeholder="جستجو در آیتم‌ها..."
                    autocomplete="off"
                >

            </div>

            <!-- Current Team -->

            <div
                class="wp-team-switcher"
                role="group"
                aria-label="Team selection"
            >

                <a
                    href="<?= esc(currentScriptPath() . '&team=2&tab=' . rawurlencode($currentTab)) ?>"
                    class="wp-team-btn wp-team-btn--t <?= $teamParam === '2' ? 'is-active' : '' ?>"
                    data-team="2"
                    data-wp-team="2"
                >
                    <i class="fas fa-bullseye"></i>
                    <span>T</span>
                </a>

                <a
                    href="<?= esc(currentScriptPath() . '&team=both&tab=' . rawurlencode($currentTab)) ?>"
                    class="wp-team-btn wp-team-btn--both <?= $teamParam === 'both' ? 'is-active' : '' ?>"
                    data-team="both"
                    data-wp-team="both"
                >
                    <i class="fas fa-layer-group"></i>
                    <span>Both</span>
                </a>

                <a
                    href="<?= esc(currentScriptPath() . '&team=3&tab=' . rawurlencode($currentTab)) ?>"
                    class="wp-team-btn wp-team-btn--ct <?= $teamParam === '3' ? 'is-active' : '' ?>"
                    data-team="3"
                    data-wp-team="3"
                >
                    <i class="fas fa-shield-halved"></i>
                    <span>CT</span>
                </a>

            </div>

        </div>

        <!-- Main Navigation -->

        <nav
            class="wp-tabs"
            id="mainTabs"
            aria-label="Weapon categories"
        >

            <button
                type="button"
                class="wp-tab <?= $currentTab === 'tab-inventory' ? 'is-active' : '' ?>"
                data-bs-toggle="tab"
                data-bs-target="#tab-inventory"
            >
                <i class="fas fa-box"></i>
                <span>Inventory</span>
            </button>

            <button
                type="button"
                class="wp-tab <?= $currentTab === 'tab-knives' ? 'is-active' : '' ?>"
                data-bs-toggle="tab"
                data-bs-target="#tab-knives"
            >
                <i class="fas fa-cutlery"></i>
                <span>Knives</span>
            </button>

            <button
                type="button"
                class="wp-tab <?= $currentTab === 'tab-skins' ? 'is-active' : '' ?>"
                data-bs-toggle="tab"
                data-bs-target="#tab-skins"
            >
                <i class="fas fa-paint-brush"></i>
                <span>Skins</span>
            </button>

            <button
                type="button"
                class="wp-tab <?= $currentTab === 'tab-gloves' ? 'is-active' : '' ?>"
                data-bs-toggle="tab"
                data-bs-target="#tab-gloves"
            >
                <i class="fas fa-hand-paper"></i>
                <span>Gloves</span>
            </button>

            <button
                type="button"
                class="wp-tab <?= $currentTab === 'tab-agents' ? 'is-active' : '' ?>"
                data-bs-toggle="tab"
                data-bs-target="#tab-agents"
            >
                <i class="fas fa-user-secret"></i>
                <span>Agents</span>
            </button>

            <button
                type="button"
                class="wp-tab <?= $currentTab === 'tab-music' ? 'is-active' : '' ?>"
                data-bs-toggle="tab"
                data-bs-target="#tab-music"
            >
                <i class="fas fa-music"></i>
                <span>Music</span>
            </button>

            <button
                type="button"
                class="wp-tab <?= $currentTab === 'tab-pins' ? 'is-active' : '' ?>"
                data-bs-toggle="tab"
                data-bs-target="#tab-pins"
            >
                <i class="fas fa-thumbtack"></i>
                <span>Pins</span>
            </button>

        </nav>

    </header>


    <!-- =========================================================
         PAGE CONTENT
    ========================================================== -->

    <main
        class="wp-main page-fader ready"
        id="mainPageFader"
    >

        <div class="tab-content">

        <?php if (empty($steamid)): ?>

            <section class="wp-empty-state">

                <div class="wp-empty-icon">
                    <i class="fas fa-palette"></i>
                </div>

                <h2>
                    لطفاً از سایت اصلی وارد شوید.
                </h2>

            </section>

        <?php else: ?>


        <!-- =====================================================
             KNIVES
        ====================================================== -->

        <section
            class="tab-pane fade <?= $currentTab === 'tab-knives' ? 'show active' : '' ?>"
            id="tab-knives"
        >

            <div
                class="wp-items-grid"
                id="knivesContainer"
            >

                <?php foreach ($knifes as $key => $knife): ?>

                    <article
                        class="wp-item knife-item"
                        data-name="<?= esc(strtolower($knife['paint_name'])) ?>"
                    >

                        <div class="wp-card">

                            <div class="wp-card-media">

                                <?= imageMarkup(
                                    (string)($knife['image_url'] ?? ''),
                                    $knife['paint_name'] ?? ''
                                ) ?>

                            </div>

                            <div class="wp-card-content">

                                <h3 class="wp-card-title">
                                    <?= esc($knife['paint_name']) ?>
                                </h3>

                                <form
                                    method="post"
                                    class="wp-auto-submit wp-card-action" data-wp-native-submit="true"
                                >

                                    <input
                                        type="hidden"
                                        name="active_tab"
                                        value="tab-knives"
                                    >

                                    <input
                                        type="hidden"
                                        name="team"
                                        value="<?= esc($teamParam) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="forma"
                                        value="knife-<?= esc($key) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="wp-btn wp-btn--primary wp-btn--sm"
                                    >
                                        <i class="fas fa-check"></i>
                                        Select
                                    </button>

                                </form>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        </section>


        <!-- =====================================================
             SKINS
        ====================================================== -->

        <section
            class="tab-pane fade <?= $currentTab === 'tab-skins' ? 'show active' : '' ?>"
            id="tab-skins"
        >

            <div class="wp-section-head">

                <div>

                    <span class="wp-section-kicker">
                        <i class="fas fa-paint-brush"></i>
                        Weapon Skins
                    </span>

                    <h2 class="wp-section-title">
                        انتخاب اسکین سلاح
                    </h2>

                </div>

                <div class="wp-section-meta">

                    <span class="wp-meta-pill">
                        <i class="fas fa-layer-group"></i>
                        <?= $skinTotalItems ?> آیتم
                    </span>

                    <span class="wp-meta-pill">
                        <i class="fas fa-table-cells-large"></i>
                        <?= $skinPerPage ?> در صفحه
                    </span>

                    <span class="wp-meta-pill">
                        <i class="fas fa-circle-info"></i>
                        صفحه <?= $skinPage ?> از <?= $skinTotalPages ?>
                    </span>

                </div>

            </div>

            <div
                class="page-fader wp-grid-fader"
                id="skinsFade"
            >

                <div
                    class="wp-items-grid wp-items-grid--skins"
                    id="skinsContainer"
                >

                    <?php foreach ($skinPageItems as $item): ?>

                        <?php

                        $def = $item['def'];
                        $default = $item['weapon'];

                        $hasSelectedSkin =
                            isset($selectedSkins[$def]);

                        $selectedPaintId =
                            $hasSelectedSkin
                                ? $selectedSkins[$def]['weapon_paint_id']
                                : null;

                        $selectedSkin =
                            (
                                $hasSelectedSkin
                                && isset(
                                    $skins[$def][$selectedPaintId]
                                )
                            )
                                ? $skins[$def][$selectedPaintId]
                                : null;

                        $displayImage =
                            $selectedSkin['image_url']
                            ?? $default['image_url']
                            ?? '';

                        $displayTitle =
                            $selectedSkin['paint_name']
                            ?? $default['paint_name']
                            ?? '';

                        $weaponName =
                            $default['weapon_name']
                            ?? '';

                        ?>

                        <article
                            class="wp-item skin-item"
                            data-def="<?= esc($def) ?>"
                            data-name="<?= esc(strtolower($weaponName)) ?>"
                        >

                            <button
                                type="button"
                                class="wp-card wp-card--clickable skin-card"
                                data-wp-modal-trigger="1"
                                data-bs-target="#skinModal"
                                data-def="<?= esc($def) ?>"
                            >

                                <span class="wp-card-glow"></span>

                                <span class="wp-card-media">

                                    <?= imageMarkup(
                                        (string)$displayImage,
                                        $displayTitle
                                    ) ?>

                                </span>

                                <span class="wp-card-content">

                                    <span class="wp-card-title">
                                        <?= esc($displayTitle) ?>
                                    </span>

                                    <span class="wp-card-subtitle">
                                        <?= esc($weaponName) ?>
                                    </span>

                                </span>

                            </button>

                        </article>

                    <?php endforeach; ?>

                </div>

            </div>

            <div class="wp-pagination-wrap">

                <div class="wp-pagination-info">
                    نمایش
                    <?= $skinStartIndex ?>
                    تا
                    <?= $skinEndIndex ?>
                    از
                    <?= $skinTotalItems ?>
                </div>

                <?= renderPagination(
                    'skins',
                    $teamParam,
                    $skinPage,
                    $skinTotalPages
                ) ?>

            </div>

        </section>


        <!-- =====================================================
             GLOVES
        ====================================================== -->

        <section
            class="tab-pane fade <?= $currentTab === 'tab-gloves' ? 'show active' : '' ?>"
            id="tab-gloves"
        >

            <div class="wp-section-head">

                <div>

                    <span class="wp-section-kicker">
                        <i class="fas fa-hand-paper"></i>
                        Gloves
                    </span>

                    <h2 class="wp-section-title">
                        دستکش‌های شما
                    </h2>

                </div>

                <div class="wp-section-meta">

                    <span class="wp-meta-pill">
                        <i class="fas fa-users"></i>
                        <?= $currentTeamLabel ?>
                    </span>

                </div>

            </div>

            <div
                class="wp-items-grid"
                id="glovesContainer"
            >

                <?php foreach ($displayTeamList as $displayTeam): ?>

                    <?php foreach ($allGloves as $defindex => $paints): ?>

                        <?php

                        $currentPaint =
                            $selectedGloves[$displayTeam][$defindex]
                            ?? null;

                        $firstPaint = reset($paints);

                        $displayImage =
                            $defindex == 0
                                ? (
                                    $displayTeam == 2
                                        ? IMAGE_DEFAULT_GLOVE_T
                                        : IMAGE_DEFAULT_GLOVE_CT
                                )
                                : (
                                    $currentPaint
                                    && isset(
                                        $paints[$currentPaint]
                                    )
                                        ? $paints[$currentPaint]['image_url']
                                        : (
                                            $firstPaint['image_url']
                                            ?? ''
                                        )
                                );

                        $displayName =
                            $currentPaint
                            && isset($paints[$currentPaint])
                                ? $paints[$currentPaint]['name']
                                : (
                                    $firstPaint['name']
                                    ?? 'Unknown'
                                );

                        $teamSuffix =
                            $teamParam === 'both'
                                ? ' (' . (
                                    $displayTeam == 2
                                        ? 'T'
                                        : 'CT'
                                ) . ')'
                                : '';

                        ?>

                        <article
                            class="wp-item glove-item"
                            data-name="<?= esc(strtolower($displayName)) ?>"
                        >

                            <button
                                type="button"
                                class="wp-card wp-card--clickable"
                                data-wp-modal-trigger="1"
                                data-bs-target="#gloveModal-<?= esc($defindex) ?>"
                            >

                                <span class="wp-card-media">

                                    <?= imageMarkup(
                                        (string)$displayImage,
                                        $displayName
                                    ) ?>

                                </span>

                                <span class="wp-card-content">

                                    <span class="wp-card-title">
                                        <?= esc($displayName . $teamSuffix) ?>
                                    </span>

                                    <span class="wp-btn wp-btn--secondary wp-btn--sm">
                                        Change Paint
                                    </span>

                                </span>

                            </button>

                        </article>

                    <?php endforeach; ?>

                <?php endforeach; ?>

            </div>

        </section>


        <!-- =====================================================
             AGENTS
        ====================================================== -->

        <section
            class="tab-pane fade <?= $currentTab === 'tab-agents' ? 'show active' : '' ?>"
            id="tab-agents"
        >

            <div class="wp-section-head">

                <div>

                    <span class="wp-section-kicker">
                        <i class="fas fa-user-secret"></i>
                        Agents
                    </span>

                    <h2 class="wp-section-title">
                        انتخاب Agent
                    </h2>

                </div>

            </div>

            <div
                class="wp-items-grid"
                id="agentsContainer"
            >

                <?php foreach ($agentTeams as $side => $agentsList): ?>

                    <?php if (empty($agentsList)) continue; ?>

                    <?php foreach ($agentsList as $id => $agent): ?>

                        <?php

                        $agentName =
                            (string)(
                                $agent['name']
                                ?? 'Unknown Agent'
                            );

                        $teamSuffix =
                            $teamParam === 'both'
                                ? ' (' . (
                                    $side === 't'
                                        ? 'T'
                                        : 'CT'
                                ) . ')'
                                : '';

                        ?>

                        <article
                            class="wp-item agent-item"
                            data-name="<?= esc(strtolower($agentName)) ?>"
                        >

                            <div class="wp-card">

                                <div class="wp-card-media">

                                    <?= imageMarkup(
                                        (string)($agent['image_url'] ?? ''),
                                        $agentName
                                    ) ?>

                                </div>

                                <div class="wp-card-content">

                                    <h3 class="wp-card-title">
                                        <?= esc(
                                            $agentName
                                            . $teamSuffix
                                        ) ?>
                                    </h3>

                                    <form
                                        method="post"
                                        class="wp-auto-submit wp-card-action" data-wp-native-submit="true"
                                    >

                                        <input
                                            type="hidden"
                                            name="active_tab"
                                            value="tab-agents"
                                        >

                                        <input
                                            type="hidden"
                                            name="team"
                                            value="<?= esc($teamParam) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="agent_id"
                                            value="<?= esc($id) ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="wp-btn wp-btn--primary wp-btn--sm"
                                        >
                                            <i class="fas fa-check"></i>
                                            Select
                                        </button>

                                    </form>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                <?php endforeach; ?>

            </div>

        </section>


        <!-- =====================================================
             MUSIC
        ====================================================== -->

        <section
            class="tab-pane fade <?= $currentTab === 'tab-music' ? 'show active' : '' ?>"
            id="tab-music"
        >

            <div class="wp-section-head">

                <div>

                    <span class="wp-section-kicker">
                        <i class="fas fa-music"></i>
                        Music
                    </span>

                    <h2 class="wp-section-title">
                        Music Kits
                    </h2>

                </div>

            </div>

            <div
                class="wp-items-grid"
                id="musicContainer"
            >

                <?php foreach ($musicTeams as $displayTeam): ?>

                    <?php foreach ($musicData as $id => $music): ?>

                        <?php

                        $musicName =
                            (string)(
                                $music['name']
                                ?? 'Unknown Music'
                            );

                        $teamSuffix =
                            $teamParam === 'both'
                                ? ' (' . (
                                    $displayTeam == 2
                                        ? 'T'
                                        : 'CT'
                                ) . ')'
                                : '';

                        ?>

                        <article
                            class="wp-item music-item"
                            data-name="<?= esc(strtolower($musicName)) ?>"
                        >

                            <div class="wp-card">

                                <div class="wp-card-media">

                                    <?= imageMarkup(
                                        (string)($music['image_url'] ?? ''),
                                        $musicName
                                    ) ?>

                                </div>

                                <div class="wp-card-content">

                                    <h3 class="wp-card-title">
                                        <?= esc(
                                            $musicName
                                            . $teamSuffix
                                        ) ?>
                                    </h3>

                                    <form
                                        method="post"
                                        class="wp-auto-submit wp-card-action" data-wp-native-submit="true"
                                    >

                                        <input
                                            type="hidden"
                                            name="active_tab"
                                            value="tab-music"
                                        >

                                        <input
                                            type="hidden"
                                            name="team"
                                            value="<?= esc($teamParam) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="music_id"
                                            value="<?= esc($id) ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="wp-btn wp-btn--primary wp-btn--sm"
                                        >
                                            <i class="fas fa-check"></i>
                                            Select
                                        </button>

                                    </form>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                <?php endforeach; ?>

            </div>

        </section>


        <!-- =====================================================
             PINS
        ====================================================== -->

        <section
            class="tab-pane fade <?= $currentTab === 'tab-pins' ? 'show active' : '' ?>"
            id="tab-pins"
        >

            <div class="wp-section-head">

                <div>

                    <span class="wp-section-kicker">
                        <i class="fas fa-thumbtack"></i>
                        Pins
                    </span>

                    <h2 class="wp-section-title">
                        Player Pins
                    </h2>

                </div>

                <div class="wp-section-meta">

                    <span class="wp-meta-pill">
                        <i class="fas fa-layer-group"></i>
                        <?= $pinTotalItems ?> آیتم
                    </span>

                    <span class="wp-meta-pill">
                        <i class="fas fa-table-cells-large"></i>
                        <?= $pinPerPage ?> در صفحه
                    </span>

                    <span class="wp-meta-pill">
                        <i class="fas fa-circle-info"></i>
                        صفحه <?= $pinPage ?>
                        از <?= $pinTotalPages ?>
                    </span>

                </div>

            </div>

            <div
                class="page-fader wp-grid-fader"
                id="pinsFade"
            >

                <div
                    class="wp-items-grid"
                    id="pinsContainer"
                >

                    <?php foreach ($pinTeams as $displayTeam): ?>

                        <?php foreach ($pinPageItems as $pin): ?>

                            <?php

                            $id =
                                (int)(
                                    $pin['id']
                                    ?? 0
                                );

                            $pinName =
                                (string)(
                                    $pin['name']
                                    ?? 'Unknown Pin'
                                );

                            $teamSuffix =
                                $teamParam === 'both'
                                    ? ' (' . (
                                        $displayTeam == 2
                                            ? 'T'
                                            : 'CT'
                                    ) . ')'
                                    : '';

                            ?>

                            <article
                                class="wp-item pin-item"
                                data-name="<?= esc(strtolower($pinName)) ?>"
                            >

                                <div class="wp-card">

                                    <div class="wp-card-media">

                                        <?= imageMarkup(
                                            (string)($pin['image_url'] ?? ''),
                                            $pinName
                                        ) ?>

                                    </div>

                                    <div class="wp-card-content">

                                        <h3 class="wp-card-title">
                                            <?= esc(
                                                $pinName
                                                . $teamSuffix
                                            ) ?>
                                        </h3>

                                        <form
                                            method="post"
                                            class="wp-auto-submit wp-card-action" data-wp-native-submit="true"
                                        >

                                            <input
                                                type="hidden"
                                                name="active_tab"
                                                value="tab-pins"
                                            >

                                            <input
                                                type="hidden"
                                                name="team"
                                                value="<?= esc($teamParam) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="pin_page"
                                                value="<?= $pinPage ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="pin_id"
                                                value="<?= $id ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="wp-btn wp-btn--primary wp-btn--sm"
                                            >
                                                <i class="fas fa-check"></i>
                                                Select
                                            </button>

                                        </form>

                                    </div>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    <?php endforeach; ?>

                </div>

            </div>

            <div class="wp-pagination-wrap">

                <div class="wp-pagination-info">

                    نمایش
                    <?= $pinStartIndex ?>
                    تا
                    <?= $pinEndIndex ?>
                    از
                    <?= $pinTotalItems ?>

                </div>

                <?= renderPagination(
                    'pins',
                    $teamParam,
                    $pinPage,
                    $pinTotalPages
                ) ?>

            </div>

        </section>


        <!-- =====================================================
             INVENTORY
        ====================================================== -->

        <section
            class="tab-pane fade <?= $currentTab === 'tab-inventory' ? 'show active' : '' ?>"
            id="tab-inventory"
        >

            <div class="wp-section-head">

                <div>

                    <span class="wp-section-kicker">
                        <i class="fas fa-box-open"></i>
                        Inventory
                    </span>

                    <h2 class="wp-section-title">
                        آیتم‌های انتخاب‌شده شما
                    </h2>

                </div>

                <div class="wp-section-meta">

                    <span class="wp-meta-pill">
                        <i class="fas fa-users"></i>
                        <?= $currentTeamLabel ?>
                    </span>

                </div>

            </div>

            <div class="wp-items-grid">

                <!-- Current Knife -->

                <?php if ($currentKnife): ?>

                    <article class="wp-item">

                        <div class="wp-card">

                            <div class="wp-card-media">

                                <?= imageMarkup(
                                    (string)($currentKnife['image_url'] ?? ''),
                                    $currentKnife['paint_name'] ?? ''
                                ) ?>

                            </div>

                            <div class="wp-card-content">

                                <h3 class="wp-card-title">
                                    <?= esc($currentKnife['paint_name']) ?>
                                </h3>

                                <form method="post" class="wp-auto-submit" data-wp-native-submit="true">

                                    <input
                                        type="hidden"
                                        name="active_tab"
                                        value="tab-inventory"
                                    >

                                    <input
                                        type="hidden"
                                        name="team"
                                        value="<?= esc($teamParam) ?>"
                                    >

                                    <select
                                        name="forma"
                                        class="wp-select"
                                       
                                    >

                                        <?php foreach ($knifes as $key => $knife): ?>

                                            <option
                                                value="knife-<?= esc($key) ?>"
                                                <?= $currentKnife['weapon_name'] === $knife['weapon_name']
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= esc($knife['paint_name']) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </form>

                            </div>

                        </div>

                    </article>

                <?php endif; ?>


                <!-- Selected Skins -->

                <?php foreach ($weaponsList as $item): ?>

                    <?php

                    $def = $item['def'];

                    if (!isset($selectedSkins[$def])) {
                        continue;
                    }

                    $sel =
                        $selectedSkins[$def];

                    $paintId =
                        $sel['weapon_paint_id'];

                    $skin =
                        $skins[$def][$paintId]
                        ?? null;

                    if (!$skin) {
                        continue;
                    }

                    ?>

                    <article class="wp-item">

                        <button
                            type="button"
                            class="wp-card wp-card--clickable"
                            data-wp-modal-trigger="1"
                            data-bs-target="#skinModal"
                            data-def="<?= esc($def) ?>"
                        >

                            <div class="wp-card-media">

                                <?= imageMarkup(
                                    (string)($skin['image_url'] ?? ''),
                                    $skin['paint_name'] ?? ''
                                ) ?>

                            </div>

                            <div class="wp-card-content">

                                <span class="wp-card-title">
                                    <?= esc($skin['paint_name']) ?>
                                </span>

                                <span class="wp-btn wp-btn--secondary wp-btn--sm">
                                    تغییر
                                </span>

                            </div>

                        </button>

                    </article>

                <?php endforeach; ?>


                <!-- Selected Gloves -->

                <?php foreach ([2, 3] as $t): ?>

                    <?php

                    if (
                        empty(
                            $selectedGloves[$t]
                        )
                    ) {
                        continue;
                    }

                    ?>

                    <?php foreach (
                        $selectedGloves[$t]
                        as $defindex => $paintId
                    ): ?>

                        <?php

                        $paints =
                            $allGloves[$defindex]
                            ?? [];

                        $paintInfo =
                            $paints[$paintId]
                            ?? null;

                        if (!$paintInfo) {
                            continue;
                        }

                        $img =
                            $defindex == 0
                                ? (
                                    $t == 2
                                        ? IMAGE_DEFAULT_GLOVE_T
                                        : IMAGE_DEFAULT_GLOVE_CT
                                )
                                : (
                                    $paintInfo['image_url']
                                    ?? ''
                                );

                        ?>

                        <article class="wp-item">

                            <button
                                type="button"
                                class="wp-card wp-card--clickable"
                                data-wp-modal-trigger="1"
                                data-bs-target="#gloveModal-<?= esc($defindex) ?>"
                            >

                                <div class="wp-card-media">

                                    <?= imageMarkup(
                                        (string)$img,
                                        $paintInfo['name'] ?? ''
                                    ) ?>

                                </div>

                                <div class="wp-card-content">

                                    <span class="wp-card-title">
                                        <?= esc(
                                            $paintInfo['name']
                                            . ' ('
                                            . (
                                                $t == 2
                                                    ? 'T'
                                                    : 'CT'
                                            )
                                            . ')'
                                        ) ?>
                                    </span>

                                    <span class="wp-btn wp-btn--secondary wp-btn--sm">
                                        تغییر رنگ
                                    </span>

                                </div>

                            </button>

                        </article>

                    <?php endforeach; ?>

                <?php endforeach; ?>


                <!-- Selected T Agent -->

                <?php if ($selectedAgentT): ?>

                    <?php
                    $agentT =
                        $agentsData['t'][$selectedAgentT]
                        ?? null;
                    ?>

                    <article class="wp-item">

                        <div class="wp-card">

                            <div class="wp-card-media">

                                <?= imageMarkup(
                                    (string)($agentT['image_url'] ?? ''),
                                    $agentT['name'] ?? 'T Agent'
                                ) ?>

                            </div>

                            <div class="wp-card-content">

                                <h3 class="wp-card-title">
                                    <?= esc(
                                        $agentT['name']
                                        ?? 'T Agent'
                                    ) ?>
                                </h3>

                                <form method="post" class="wp-auto-submit" data-wp-native-submit="true">

                                    <input
                                        type="hidden"
                                        name="active_tab"
                                        value="tab-inventory"
                                    >

                                    <input
                                        type="hidden"
                                        name="team"
                                        value="2"
                                    >

                                    <select
                                        name="agent_id"
                                        class="wp-select"
                                       
                                    >

                                        <?php foreach (
                                            $agentsData['t']
                                            ?? []
                                            as $id => $a
                                        ): ?>

                                            <option
                                                value="<?= esc($id) ?>"
                                                <?= $selectedAgentT == $id
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= esc($a['name']) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </form>

                            </div>

                        </div>

                    </article>

                <?php endif; ?>


                <!-- Selected CT Agent -->

                <?php if ($selectedAgentCT): ?>

                    <?php
                    $agentCT =
                        $agentsData['ct'][$selectedAgentCT]
                        ?? null;
                    ?>

                    <article class="wp-item">

                        <div class="wp-card">

                            <div class="wp-card-media">

                                <?= imageMarkup(
                                    (string)($agentCT['image_url'] ?? ''),
                                    $agentCT['name'] ?? 'CT Agent'
                                ) ?>

                            </div>

                            <div class="wp-card-content">

                                <h3 class="wp-card-title">
                                    <?= esc(
                                        $agentCT['name']
                                        ?? 'CT Agent'
                                    ) ?>
                                </h3>

                                <form method="post" class="wp-auto-submit" data-wp-native-submit="true">

                                    <input
                                        type="hidden"
                                        name="active_tab"
                                        value="tab-inventory"
                                    >

                                    <input
                                        type="hidden"
                                        name="team"
                                        value="3"
                                    >

                                    <select
                                        name="agent_id"
                                        class="wp-select"
                                       
                                    >

                                        <?php foreach (
                                            $agentsData['ct']
                                            ?? []
                                            as $id => $a
                                        ): ?>

                                            <option
                                                value="<?= esc($id) ?>"
                                                <?= $selectedAgentCT == $id
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= esc($a['name']) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </form>

                            </div>

                        </div>

                    </article>

                <?php endif; ?>


                <!-- Selected Music -->

                <?php foreach ([2, 3] as $t): ?>

                    <?php

                    if (
                        empty(
                            $selectedMusicIds[$t]
                        )
                    ) {
                        continue;
                    }

                    $mid =
                        $selectedMusicIds[$t];

                    $music =
                        $musicData[$mid]
                        ?? null;

                    if (!$music) {
                        continue;
                    }

                    ?>

                    <article class="wp-item">

                        <div class="wp-card">

                            <div class="wp-card-media">

                                <?= imageMarkup(
                                    (string)($music['image_url'] ?? ''),
                                    $music['name'] ?? ''
                                ) ?>

                            </div>

                            <div class="wp-card-content">

                                <h3 class="wp-card-title">
                                    <?= esc(
                                        $music['name']
                                        . ' ('
                                        . (
                                            $t == 2
                                                ? 'T'
                                                : 'CT'
                                        )
                                        . ')'
                                    ) ?>
                                </h3>

                                <form method="post" class="wp-auto-submit" data-wp-native-submit="true">

                                    <input
                                        type="hidden"
                                        name="active_tab"
                                        value="tab-inventory"
                                    >

                                    <input
                                        type="hidden"
                                        name="team"
                                        value="<?= $t ?>"
                                    >

                                    <select
                                        name="music_id"
                                        class="wp-select"
                                       
                                    >

                                        <?php foreach (
                                            $musicData
                                            as $id => $m
                                        ): ?>

                                            <option
                                                value="<?= esc($id) ?>"
                                                <?= $mid == $id
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= esc($m['name']) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </form>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>


                <!-- Selected Pins -->

                <?php foreach ([2, 3] as $t): ?>

                    <?php

                    if (
                        empty(
                            $selectedPinIds[$t]
                        )
                    ) {
                        continue;
                    }

                    $pid =
                        $selectedPinIds[$t];

                    $pin =
                        $pinsData[$pid]
                        ?? null;

                    if (!$pin) {
                        continue;
                    }

                    ?>

                    <article class="wp-item">

                        <div class="wp-card">

                            <div class="wp-card-media">

                                <?= imageMarkup(
                                    (string)($pin['image_url'] ?? ''),
                                    $pin['name'] ?? ''
                                ) ?>

                            </div>

                            <div class="wp-card-content">

                                <h3 class="wp-card-title">
                                    <?= esc(
                                        $pin['name']
                                        . ' ('
                                        . (
                                            $t == 2
                                                ? 'T'
                                                : 'CT'
                                        )
                                        . ')'
                                    ) ?>
                                </h3>

                                <form method="post" class="wp-auto-submit" data-wp-native-submit="true">

                                    <input
                                        type="hidden"
                                        name="active_tab"
                                        value="tab-inventory"
                                    >

                                    <input
                                        type="hidden"
                                        name="team"
                                        value="<?= $t ?>"
                                    >

                                    <select
                                        name="pin_id"
                                        class="wp-select"
                                       
                                    >

                                        <?php foreach (
                                            $pinsData
                                            as $id => $p
                                        ): ?>

                                            <option
                                                value="<?= esc($id) ?>"
                                                <?= $pid == $id
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= esc($p['name']) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </form>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        </section>

        <?php endif; ?>

        </div>

    </main>

</div>


<!-- =========================================================
     SKIN MODAL
========================================================== -->

<div
    class="modal fade wp-modal"
    id="skinModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-xl skin-modal-dialog">

        <div class="modal-content bg-site-dark">

            <div class="modal-header wp-modal-header">

                <div class="wp-modal-title-wrap">

                    <span class="wp-modal-eyebrow">
                        <i class="fas fa-paint-brush"></i>
                        Weapon Customization
                    </span>

                    <h5
                        id="skinModalTitle"
                        class="wp-modal-title"
                    ></h5>

                </div>

                <button
                    type="button"
                    class="modal-x-btn"
                    data-wp-modal-close="1"
                    aria-label="Close"
                >
                    <i class="fas fa-xmark"></i>
                </button>

            </div>

            <div
                class="modal-body wp-modal-body"
                id="skinModalBody"
            ></div>

        </div>

    </div>

</div>


<!-- =========================================================
     STICKER MODAL
========================================================== -->

<div
    class="modal fade wp-modal"
    id="stickerModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-xl selection-modal-dialog">

        <div class="modal-content bg-site-dark">

            <div class="modal-header wp-modal-header">

                <div class="wp-modal-title-wrap">

                    <span class="wp-modal-eyebrow">
                        <i class="fas fa-sticky-note"></i>
                        Skin Add-on
                    </span>

                    <h5 class="wp-modal-title">
                        Select Sticker
                    </h5>

                </div>

                <button
                    type="button"
                    class="modal-x-btn"
                    data-wp-modal-close="1"
                    aria-label="Close"
                >
                    <i class="fas fa-xmark"></i>
                </button>

            </div>

            <div
                class="modal-body wp-modal-body"
                id="stickerModalBody"
            ></div>

        </div>

    </div>

</div>


<!-- =========================================================
     KEYCHAIN MODAL
========================================================== -->

<div
    class="modal fade wp-modal"
    id="keychainModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-xl selection-modal-dialog">

        <div class="modal-content bg-site-dark">

            <div class="modal-header wp-modal-header">

                <div class="wp-modal-title-wrap">

                    <span class="wp-modal-eyebrow">
                        <i class="fas fa-key"></i>
                        Skin Add-on
                    </span>

                    <h5 class="wp-modal-title">
                        Select Keychain
                    </h5>

                </div>

                <button
                    type="button"
                    class="modal-x-btn"
                    data-wp-modal-close="1"
                    aria-label="Close"
                >
                    <i class="fas fa-xmark"></i>
                </button>

            </div>

            <div
                class="modal-body wp-modal-body"
                id="keychainModalBody"
            ></div>

        </div>

    </div>

</div>


<!-- =========================================================
     GLOVE MODALS
========================================================== -->

<?php foreach ($allGloves as $defindex => $paints): ?>

    <?php
    $gloveTitle =
        reset($paints)['name']
        ?? 'دستکش';
    ?>

    <div
        class="modal fade wp-modal"
        id="gloveModal-<?= esc($defindex) ?>"
        tabindex="-1"
        aria-hidden="true"
    >

        <div class="modal-dialog selection-modal-dialog">

            <div class="modal-content bg-site-dark">

                <div class="modal-header wp-modal-header">

                    <div class="wp-modal-title-wrap">

                        <span class="wp-modal-eyebrow">
                            <i class="fas fa-hand-paper"></i>
                            Gloves
                        </span>

                        <h5 class="wp-modal-title">
                            <?= esc($gloveTitle) ?>
                        </h5>

                    </div>

                    <button
                        type="button"
                        class="modal-x-btn"
                        data-wp-modal-close="1"
                        aria-label="Close"
                    >
                        <i class="fas fa-xmark"></i>
                    </button>

                </div>

                <div class="modal-body wp-modal-body">

                    <form method="post" class="wp-auto-submit" data-wp-native-submit="true">

                        <input
                            type="hidden"
                            name="active_tab"
                            value="tab-gloves"
                        >

                        <input
                            type="hidden"
                            name="team"
                            value="<?= esc($teamParam) ?>"
                        >

                        <input
                            type="hidden"
                            name="glove_defindex"
                            value="<?= esc($defindex) ?>"
                        >

                        <div class="selection-grid selection-grid--glove">

                            <?php foreach ($paints as $paint => $info): ?>

                                <?php
                                $currentPaint =
                                    $selectedGloves[$team][$defindex]
                                    ?? null;
                                ?>

                                <label
                                    class="selection-card selection-card--glove <?= $currentPaint == $paint ? 'is-selected' : '' ?>"
                                >

                                    <input
                                        type="radio"
                                        name="glove_paint"
                                        value="<?= esc($paint) ?>"
                                        <?= $currentPaint == $paint ? 'checked' : '' ?>
                                    >

                                    <div class="selection-image">

                                        <?= imageMarkup(
                                            (string)($info['image_url'] ?? ''),
                                            $info['name'] ?? ''
                                        ) ?>

                                    </div>

                                    <div class="selection-title">
                                        <?= esc($info['name']) ?>
                                    </div>

                                    <div class="selection-meta">

                                        <i class="fas fa-hashtag"></i>

                                        Paint ID:

                                        <?= esc((string)$paint) ?>

                                    </div>

                                </label>

                            <?php endforeach; ?>

                        </div>

                        <div class="selection-footer">

                            <button
                                type="submit"
                                class="wp-btn wp-btn--primary"
                            >

                                <i class="fas fa-check-circle"></i>

                                اعمال تغییرات

                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

<?php endforeach; ?>


<!-- =========================================================
     JAVASCRIPT CONFIG
========================================================== -->

<script>
window.WeaponPaintsConfig = {
    weapons: <?= json_encode($weapons, $jsonFlags) ?>,

    skins: <?= json_encode($skins, $jsonFlags) ?>,

    selectedSkins: <?= json_encode($selectedSkins, $jsonFlags) ?>,

    skinDetails: <?= json_encode($skinDetails, $jsonFlags) ?>,

    stickersData: <?= json_encode($stickersData, $jsonFlags) ?>,

    keychainsData: <?= json_encode($keychainsData, $jsonFlags) ?>,

    currentTeamParam: <?= json_encode($teamParam, $jsonFlags) ?>,

    currentTab: <?= json_encode($currentTab, $jsonFlags) ?>,

    currentSkinPage: <?= (int)$skinPage ?>,

    currentPinPage: <?= (int)$pinPage ?>,

    currentPhpFile: <?= json_encode(currentScriptPath(), $jsonFlags) ?>
};
</script>
