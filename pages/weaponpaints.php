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
    /*
     * header('Location: ...') can't be used here: by this point
     * index.php has already sent the page's HTML (head, CSS links,
     * header, etc.), so PHP can no longer send redirect headers.
     * A JS redirect works for both a normal page load AND the AJAX
     * navigation in header.js (which re-executes inline <script>
     * tags found in the fetched fragment).
     */
    echo '<script>window.location.replace("/index.php?page=login");</script>';
    exit;
}

$steamid = (string)($_SESSION['steamid'] ?? '');

if ($steamid === '') {
    echo '<div class="wp-auth-state">اطلاعات کاربر نامعتبر.</div>';
    return;
}

if (
    !isset($_SESSION['wp_csrf_token']) ||
    !is_string($_SESSION['wp_csrf_token']) ||
    !preg_match('/^[a-f0-9]{64}$/D', $_SESSION['wp_csrf_token'])
) {
    $_SESSION['wp_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['wp_csrf_token'];

session_write_close();

/*
|--------------------------------------------------------------------------
| Dependencies
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../class/config.php';
require_once __DIR__ . '/../class/WeaponPaintsRepository.php';
require_once __DIR__ . '/../class/utils.php';

if (!defined('IMAGE_DEFAULT_GLOVE_T')) {
    define('IMAGE_DEFAULT_GLOVE_T', '');
}

if (!defined('IMAGE_DEFAULT_GLOVE_CT')) {
    define('IMAGE_DEFAULT_GLOVE_CT', '');
}

try {
    $db = new WeaponPaintsRepository();
} catch (Throwable $e) {
    error_log('[FragCity] Weapon-paints DB initialization failed: ' . $e->getMessage());
    http_response_code(503);
    echo '<div class="wp-auth-state" role="alert">اتصال سرویس اسکین‌ها برقرار نشد. تنظیمات API یا دیتابیس را بررسی کنید.</div>';
    return;
}

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
        'tab-rifles',
        'tab-smg',
        'tab-heavy',
        'tab-pistol',
        'tab-sniper',
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
    int $page,
    string $tab = 'tab-skins'
): string {
    $teamParam = normalizeTeamSelection($teamParam);
    $tab = normalizeTabSelection($tab);
    $page = max(1, $page);

    return currentScriptPath()
        . '&team=' . rawurlencode($teamParam)
        . '&tab=' . rawurlencode($tab)
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
    int $totalPages,
    string $tab = 'tab-skins'
): string {
    $currentPage = max(1, min($currentPage, $totalPages));
    $isSkin = ($type === 'skins');

    $navigateFunction = $isSkin
        ? 'navigateSkinPage'
        : 'navigatePinPage';

    $pageUrl = static function (int $page) use ($isSkin, $teamParam, $tab): string {
        return $isSkin
            ? buildSkinPageUrl($teamParam, $page, $tab)
            : buildPinPageUrl($teamParam, $page);
    };

    $previousPage = max(1, $currentPage - 1);
    $nextPage = min($totalPages, $currentPage + 1);

    $html = '<div class="wp-pagination">';

    $html .= sprintf(
        '<a href="%s" class="wp-page-btn wp-page-btn--nav" data-wp-page="%d" data-wp-navigation="%s" aria-label="Previous page">'
        . '<i class="fas fa-angle-right"></i>'
        . '</a>',
        esc($pageUrl($previousPage)),
        $previousPage,
        $navigateFunction
    );

    for ($page = 1; $page <= $totalPages; $page++) {
        $activeClass = ($page === $currentPage)
            ? ' is-active'
            : '';

        $html .= sprintf(
            '<a href="%s" class="wp-page-btn%s" data-wp-page="%d" data-wp-navigation="%s">%d</a>',
            esc($pageUrl($page)),
            $activeClass,
            $page,
            $navigateFunction,
            $page
        );
    }

    $html .= sprintf(
        '<a href="%s" class="wp-page-btn wp-page-btn--nav" data-wp-page="%d" data-wp-navigation="%s" aria-label="Next page">'
        . '<i class="fas fa-angle-left"></i>'
        . '</a>',
        esc($pageUrl($nextPage)),
        $nextPage,
        $navigateFunction
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

try {
$playerSelection = $db->getPlayerSelection($steamid);

$skinRows = $playerSelection['skins'] ?? [];
[
    $selectedSkins,
    $skinDetails,
    $selectedGloves
] = buildSkinSelectionMaps($skinRows);

$selectedKnifeRows = $playerSelection['knife'] ?? [];
$selectedKnife = $selectedKnifeRows[0] ?? [];

$selectedAgents = $playerSelection['agents'] ?? [];
$selectedAgentT = $selectedAgents[0]['agent_t'] ?? '';
$selectedAgentCT = $selectedAgents[0]['agent_ct'] ?? '';

$selectedMusic = $playerSelection['music'] ?? [];
$selectedMusicIds = [2 => 0, 3 => 0];
foreach ($selectedMusic as $row) {
    $teamKey = (int)($row['weapon_team'] ?? 0);
    if ($teamKey === 2 || $teamKey === 3) {
        $selectedMusicIds[$teamKey] = (int)($row['music_id'] ?? 0);
    }
}

$selectedPins = $playerSelection['pins'] ?? [];
$selectedPinIds = [2 => 0, 3 => 0];
foreach ($selectedPins as $row) {
    $teamKey = (int)($row['weapon_team'] ?? 0);
    if ($teamKey === 2 || $teamKey === 3) {
        $selectedPinIds[$teamKey] = (int)($row['id'] ?? 0);
    }
}
} catch (Throwable $e) {
    error_log('[FragCity] Weapon-paints data load failed: ' . $e->getMessage());
    http_response_code(503);
    echo '<div class="wp-auth-state" role="alert">اطلاعات اسکین‌های کاربر قابل دریافت نیست. ساختار دیتابیس و جدول‌های پلاگین را بررسی کنید.</div>';
    return;
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
    $submittedCsrfToken = $_POST['wp_csrf_token'] ?? '';
    if (!is_string($submittedCsrfToken) || !hash_equals($csrfToken, $submittedCsrfToken)) {
        http_response_code(400);
        echo '<div class="wp-auth-state" role="alert">درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید و دوباره تلاش کنید.</div>';
        return;
    }

    try {
        $activeTab = (string)($_POST['active_tab'] ?? 'tab-knives');
        $save_team = normalizeTeamSelection((string)($_POST['team'] ?? '2'));
        $teams = $save_team === 'both' ? [2, 3] : [(int)$save_team];

        if (isset($_POST['glove_defindex'], $_POST['glove_paint'])) {
            $def = (int)$_POST['glove_defindex'];
            $paint = (int)$_POST['glove_paint'];
            if (!isset($allGloves[$def][$paint])) {
                http_response_code(400);
                echo '<div class="wp-auth-state" role="alert">دستکش انتخاب‌شده معتبر نیست.</div>';
                return;
            }
            $db->saveGlove($steamid, $teams, $def, $paint);
            redirectToSelf($save_team, $activeTab, $skinPage, $pinPage);
        }

        if (isset($_POST['agent_id'])) {
            $agentId = (string)$_POST['agent_id'];
            if (!isset($agentsData['t'][$agentId]) && !isset($agentsData['ct'][$agentId])) {
                http_response_code(400);
                echo '<div class="wp-auth-state" role="alert">Agent انتخاب‌شده معتبر نیست.</div>';
                return;
            }
            $db->saveAgent($steamid, $teams, $agentId);
            redirectToSelf($save_team, $activeTab, $skinPage, $pinPage);
        }

        if (isset($_POST['music_id'])) {
            $musicId = (int)$_POST['music_id'];
            if (!isset($musicData[$musicId])) {
                http_response_code(400);
                echo '<div class="wp-auth-state" role="alert">Music Kit انتخاب‌شده معتبر نیست.</div>';
                return;
            }
            $db->saveMusic($steamid, $teams, $musicId);
            redirectToSelf($save_team, $activeTab, $skinPage, $pinPage);
        }

        if (isset($_POST['pin_id'])) {
            $pinId = (int)$_POST['pin_id'];
            if (!isset($pinsData[$pinId])) {
                http_response_code(400);
                echo '<div class="wp-auth-state" role="alert">Pin انتخاب‌شده معتبر نیست.</div>';
                return;
            }
            $db->savePin($steamid, $teams, $pinId);
            redirectToSelf($save_team, $activeTab, $skinPage, $pinPage);
        }

        if (isset($_POST['forma'])) {
            $parts = explode('-', (string)$_POST['forma'], 2);

            if (($parts[0] ?? '') === 'knife') {
                $knifeKey = $parts[1] ?? '';
                if (isset($knifes[$knifeKey]['weapon_name'])) {
                    $db->saveKnife($steamid, (string)$knifes[$knifeKey]['weapon_name']);
                }
                redirectToSelf($save_team, $activeTab, $skinPage, $pinPage);
            }

            $def = (string)($parts[0] ?? '');
            $paint = (string)($parts[1] ?? '');

            $wear = max(0.0, min(1.0, (float)($_POST['wear'] ?? 0.001)));
            $seed = (int)($_POST['seed'] ?? 0);

            $nametag = trim((string)($_POST['nametag'] ?? ''));
            $nametag = $nametag === '' ? null : mb_substr($nametag, 0, 20);
            $stattrak = isset($_POST['stattrak']) ? 1 : 0;

            $stickers = [];
            for ($i = 0; $i < 5; $i++) {
                $s = $_POST['sticker'][$i] ?? [];
                if (!is_array($s)) {
                    http_response_code(400);
                    echo '<div class="wp-auth-state" role="alert">اطلاعات Sticker معتبر نیست.</div>';
                    return;
                }
                $stickerId = (int)($s['id'] ?? 0);
                if ($stickerId !== 0 && !isset($stickersData[$stickerId])) {
                    http_response_code(400);
                    echo '<div class="wp-auth-state" role="alert">Sticker انتخاب‌شده معتبر نیست.</div>';
                    return;
                }
                $stickers[] = implode(';', [
                    $stickerId,
                    0,
                    (float)($s['x'] ?? 0),
                    (float)($s['y'] ?? 0),
                    (float)($s['wear'] ?? 0.001),
                    (float)($s['scale'] ?? 1),
                    (float)($s['rotate'] ?? 0),
                ]);
            }

            $kc = $_POST['keychain'] ?? [];
            if (!is_array($kc)) {
                http_response_code(400);
                echo '<div class="wp-auth-state" role="alert">اطلاعات Keychain معتبر نیست.</div>';
                return;
            }
            $keychainId = (int)($kc['id'] ?? 0);
            if ($keychainId !== 0 && !isset($keychainsData[$keychainId])) {
                http_response_code(400);
                echo '<div class="wp-auth-state" role="alert">Keychain انتخاب‌شده معتبر نیست.</div>';
                return;
            }
            $keychainStr = implode(';', [
                $keychainId,
                (float)($kc['x'] ?? 0),
                (float)($kc['y'] ?? 0),
                (float)($kc['z'] ?? 0),
                (int)($kc['seed'] ?? 0),
            ]);

            if (isset($skins[(int)$def][(int)$paint])) {
                $selectedPaint = $skins[(int)$def][(int)$paint];

                $db->saveSkin(
                    $steamid,
                    $teams,
                    (int)$def,
                    (int)$paint,
                    $wear,
                    $seed,
                    $nametag,
                    $stattrak,
                    $stickers,
                    $keychainStr
                );

                // Choosing a knife finish also equips that knife model.
                // Defindex 0 is the separate "Default knife" reset card.
                if ((int)$def !== 0 && isset($knifes[(int)$def])) {
                    $knifeWeaponName = (string)($selectedPaint['weapon_name'] ?? '');
                    if ($knifeWeaponName !== '') {
                        $db->saveKnife($steamid, $knifeWeaponName);
                    }
                }
            }

            redirectToSelf($save_team, $activeTab, $skinPage, $pinPage);
        }
    } catch (Throwable $e) {
        error_log('[FragCity] Weapon-paints save failed: ' . $e->getMessage());
        http_response_code(500);
        echo '<div class="wp-auth-state" role="alert">ذخیره‌سازی تنظیمات اسکین انجام نشد. خطای سرویس را در لاگ PHP بررسی کنید.</div>';
        return;
    }
}
/*
|--------------------------------------------------------------------------
| Skin pagination
|--------------------------------------------------------------------------
*/

$weaponsList = [];

// Knife finishes are selected directly from the Knife tab's modal.
// Keep the complete $weapons/$skins maps for that modal, but do not
// duplicate knife families in the general firearm Skins tab.
$knifeDefindexes = array_fill_keys(
    array_map('intval', array_keys($knifes)),
    true
);

foreach ($weapons as $def => $weapon) {
    if (isset($knifeDefindexes[(int)$def])) {
        continue;
    }

    $weaponsList[] = [
        'def' => $def,
        'weapon' => $weapon
    ];
}

$skinPerPage = 24;

// Every category is a separate top-level tab. The original Skins tab remains
// the complete non-knife catalogue so uncommon weapon finishes stay reachable.
$weaponSkinTabDefinitions = [
    'tab-skins' => [
        'title' => 'All Skins',
        'icon' => 'fas fa-paint-brush',
        'defindexes' => null,
    ],
    'tab-rifles' => [
        'title' => 'Rifles',
        'icon' => 'fas fa-crosshairs',
        'defindexes' => [7, 8, 10, 13, 16, 39, 60],
    ],
    'tab-smg' => [
        'title' => 'SMG',
        'icon' => 'fas fa-bolt',
        'defindexes' => [17, 19, 23, 24, 26, 33, 34],
    ],
    'tab-heavy' => [
        'title' => 'Heavy Guns',
        'icon' => 'fas fa-fire',
        'defindexes' => [14, 25, 27, 28, 29, 35],
    ],
    'tab-pistol' => [
        'title' => 'Pistols',
        'icon' => 'fas fa-gun',
        'defindexes' => [1, 2, 3, 4, 30, 32, 36, 61, 63, 64],
    ],
    'tab-sniper' => [
        'title' => 'Snipers',
        'icon' => 'fas fa-binoculars',
        'defindexes' => [9, 11, 38, 40],
    ],
];

$requestedSkinPage = max(1, $skinPage);
$skinCategoryViews = [];

foreach ($weaponSkinTabDefinitions as $tabId => $definition) {
    $categoryItems = $weaponsList;
    $allowedDefindexes = $definition['defindexes'];

    if (is_array($allowedDefindexes)) {
        $categoryItems = array_values(array_filter(
            $weaponsList,
            static function (array $item) use ($allowedDefindexes): bool {
                $defindex = (int)($item['def'] ?? 0);
                return in_array($defindex, $allowedDefindexes, true);
            }
        ));
    }

    $totalItems = count($categoryItems);
    $totalPages = max(1, (int)ceil($totalItems / $skinPerPage));
    $categoryPage = min($requestedSkinPage, $totalPages);
    $offset = ($categoryPage - 1) * $skinPerPage;
    $pageItems = array_slice($categoryItems, $offset, $skinPerPage);

    $skinCategoryViews[$tabId] = [
        'title' => $definition['title'],
        'icon' => $definition['icon'],
        'total_items' => $totalItems,
        'total_pages' => $totalPages,
        'page' => $categoryPage,
        'page_items' => $pageItems,
        'start_index' => $totalItems > 0 ? $offset + 1 : 0,
        'end_index' => min($offset + $skinPerPage, $totalItems),
    ];
}

$activeSkinView = $skinCategoryViews[$currentTab] ?? $skinCategoryViews['tab-skins'];
$skinPage = $activeSkinView['page'];
$skinTotalItems = $activeSkinView['total_items'];
$skinTotalPages = $activeSkinView['total_pages'];
$skinPageItems = $activeSkinView['page_items'];
$skinStartIndex = $activeSkinView['start_index'];
$skinEndIndex = $activeSkinView['end_index'];

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
    ($selectedKnife['knife'] ?? '')
        ?: null;

$currentKnife = null;
$currentKnifeDefindex = null;

if ($currentKnifeName) {
    foreach ($knifes as $knifeDefindex => $knife) {
        if (
            $knife['weapon_name']
            === $currentKnifeName
        ) {
            $currentKnife = $knife;
            $currentKnifeDefindex = (int)$knifeDefindex;
            break;
        }
    }
}

// Resolve the equipped knife's saved finish for the Inventory card.
// Knife models are stored separately from paints, so the vanilla model data
// must be overlaid with the selected paint from the saved skin rows.
if (
    $currentKnifeDefindex !== null
    && $currentKnifeDefindex !== 0
) {
    $currentKnifeSkinRow = null;

    // Prefer the currently selected team, since knife finishes may be saved
    // independently for T and CT. The "Both" view resolves to T first.
    if (isset($skinDetails[$currentKnifeDefindex][$team])) {
        $currentKnifeSkinRow = $skinDetails[$currentKnifeDefindex][$team];
    } else {
        $currentKnifeSkinRow =
            $skinDetails[$currentKnifeDefindex][2]
            ?? $skinDetails[$currentKnifeDefindex][3]
            ?? $selectedSkins[$currentKnifeDefindex]
            ?? null;
    }

    if (is_array($currentKnifeSkinRow)) {
        $currentKnifePaintId = (int)($currentKnifeSkinRow['weapon_paint_id'] ?? 0);
        $currentKnifePaint = $skins[$currentKnifeDefindex][$currentKnifePaintId] ?? null;

        if (is_array($currentKnifePaint)) {
            $currentKnife['paint_name'] =
                $currentKnifePaint['paint_name']
                ?? $currentKnife['paint_name'];

            $currentKnife['image_url'] =
                $currentKnifePaint['image_url']
                ?? $currentKnife['image_url'];

            $currentKnife['weapon_paint_id'] = $currentKnifePaintId;
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

        <!-- Main toolbar: team, categories and search share one aligned row. -->

        <nav
            class="wp-tabs"
            id="mainTabs"
            aria-label="Weapon categories"
        >

            <div class="wp-category-switcher" aria-label="Item categories">
                <button
                                type="button"
                                class="wp-tab <?= $currentTab === 'tab-inventory' ? 'is-active' : '' ?>"
                                title="Inventory"
                                aria-label="Inventory"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-inventory"
                            >
                                <i class="fas fa-box"></i>
                            </button>
                
                            <button
                                type="button"
                                class="wp-tab <?= $currentTab === 'tab-knives' ? 'is-active' : '' ?>"
                                title="Knives"
                                aria-label="Knives"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-knives"
                            >
                                <i class="fas fa-cutlery"></i>
                            </button>
                
                            <button
                                type="button"
                                class="wp-tab <?= $currentTab === 'tab-skins' ? 'is-active' : '' ?>"
                                title="All Skins"
                                aria-label="All Skins"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-skins"
                            >
                                <i class="fas fa-paint-brush"></i>
                            </button>
                
                            <button
                                type="button"
                                class="wp-tab <?= $currentTab === 'tab-rifles' ? 'is-active' : '' ?>"
                                title="Rifles"
                                aria-label="Rifles"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-rifles"
                            >
                                <i class="fas fa-crosshairs"></i>
                            </button>
                
                            <button
                                type="button"
                                class="wp-tab <?= $currentTab === 'tab-smg' ? 'is-active' : '' ?>"
                                title="SMG"
                                aria-label="SMG"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-smg"
                            >
                                <i class="fas fa-bolt"></i>
                            </button>
                
                            <button
                                type="button"
                                class="wp-tab <?= $currentTab === 'tab-heavy' ? 'is-active' : '' ?>"
                                title="Heavy Guns"
                                aria-label="Heavy Guns"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-heavy"
                            >
                                <i class="fas fa-fire"></i>
                            </button>
                
                            <button
                                type="button"
                                class="wp-tab <?= $currentTab === 'tab-pistol' ? 'is-active' : '' ?>"
                                title="Pistols"
                                aria-label="Pistols"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-pistol"
                            >
                                <i class="fas fa-gun"></i>
                            </button>
                
                            <button
                                type="button"
                                class="wp-tab <?= $currentTab === 'tab-sniper' ? 'is-active' : '' ?>"
                                title="Snipers"
                                aria-label="Snipers"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-sniper"
                            >
                                <i class="fas fa-binoculars"></i>
                            </button>
                
                            <button
                                type="button"
                                class="wp-tab <?= $currentTab === 'tab-gloves' ? 'is-active' : '' ?>"
                                title="Gloves"
                                aria-label="Gloves"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-gloves"
                            >
                                <i class="fas fa-hand-paper"></i>
                            </button>
                
                            <button
                                type="button"
                                class="wp-tab <?= $currentTab === 'tab-agents' ? 'is-active' : '' ?>"
                                title="Agents"
                                aria-label="Agents"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-agents"
                            >
                                <i class="fas fa-user-secret"></i>
                            </button>
                
                            <button
                                type="button"
                                class="wp-tab <?= $currentTab === 'tab-music' ? 'is-active' : '' ?>"
                                title="Music"
                                aria-label="Music"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-music"
                            >
                                <i class="fas fa-music"></i>
                            </button>
                
                            <button
                                type="button"
                                class="wp-tab <?= $currentTab === 'tab-pins' ? 'is-active' : '' ?>"
                                title="Pins"
                                aria-label="Pins"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-pins"
                            >
                                <i class="fas fa-thumbtack"></i>
                            </button>
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

<!-- Search stays on the same toolbar row as team and category controls. -->

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

                        <div
                            class="wp-card <?= (int)$key !== 0 ? 'wp-card--clickable' : '' ?>"
                            <?php if ((int)$key !== 0): ?>
                            data-bs-toggle="modal"
                            data-bs-target="#skinModal"
                            data-def="<?= esc((string)$key) ?>"
                            role="button"
                            tabindex="0"
                            aria-label="<?= esc('Choose a skin for ' . (string)$knife['paint_name']) ?>"
                            title="Choose skin"
                            <?php endif; ?>
                        >

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

                                <?php if ((int)$key === 0): ?>

                                    <form
                                        method="post"
                                        class="wp-auto-submit wp-card-action"
                                        data-wp-native-submit="true"
                                    >
                                        <input type="hidden" name="wp_csrf_token" value="<?= esc($csrfToken) ?>">

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
                                            <i class="fas fa-rotate-left"></i>
                                            Reset to default
                                        </button>
                                    </form>

                                <?php else: ?>

                                    <div class="wp-card-action">
                                        <span class="wp-btn wp-btn--primary wp-btn--sm">
                                            <i class="fas fa-palette"></i>
                                            Choose skin
                                        </span>
                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        </section>


        <!-- =====================================================
             SKINS
        ====================================================== -->

        <?php foreach ($skinCategoryViews as $skinTabId => $skinView): ?>

        <section
            class="tab-pane fade <?= $currentTab === $skinTabId ? 'show active' : '' ?>"
            id="<?= esc($skinTabId) ?>"
        >

            <div class="wp-section-head">

                <div>

                    <span class="wp-section-kicker">
                        <i class="<?= esc($skinView['icon']) ?>"></i>
                        <?= esc($skinView['title']) ?>
                    </span>

                    <h2 class="wp-section-title">
                        انتخاب اسکین سلاح — <?= esc($skinView['title']) ?>
                    </h2>

                </div>

                <div class="wp-section-meta">

                    <span class="wp-meta-pill">
                        <i class="fas fa-layer-group"></i>
                        <?= $skinView['total_items'] ?> آیتم
                    </span>

                    <span class="wp-meta-pill">
                        <i class="fas fa-table-cells-large"></i>
                        <?= $skinPerPage ?> در صفحه
                    </span>

                    <span class="wp-meta-pill">
                        <i class="fas fa-circle-info"></i>
                        صفحه <?= $skinView['page'] ?> از <?= $skinView['total_pages'] ?>
                    </span>

                </div>

            </div>

            <div
                class="page-fader wp-grid-fader"
                id="skinsFade-<?= esc($skinTabId) ?>"
            >

                <div
                    class="wp-items-grid wp-items-grid--skins"
                    id="skinsContainer-<?= esc($skinTabId) ?>"
                    data-wp-skin-category="<?= esc($skinTabId) ?>"
                >

                    <?php foreach ($skinView['page_items'] as $item): ?>

                        <?php
                        $def = $item['def'];
                        $default = $item['weapon'];

                        $hasSelectedSkin = isset($selectedSkins[$def]);
                        $selectedPaintId = $hasSelectedSkin
                            ? $selectedSkins[$def]['weapon_paint_id']
                            : null;

                        $selectedSkin = (
                            $hasSelectedSkin
                            && isset($skins[$def][$selectedPaintId])
                        )
                            ? $skins[$def][$selectedPaintId]
                            : null;

                        $displayImage = $selectedSkin['image_url']
                            ?? $default['image_url']
                            ?? '';

                        $displayTitle = $selectedSkin['paint_name']
                            ?? $default['paint_name']
                            ?? '';

                        $weaponName = $default['weapon_name'] ?? '';
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
                    <?= $skinView['start_index'] ?>
                    تا
                    <?= $skinView['end_index'] ?>
                    از
                    <?= $skinView['total_items'] ?>
                </div>

                <?= renderPagination(
                    'skins',
                    $teamParam,
                    $skinView['page'],
                    $skinView['total_pages'],
                    $skinTabId
                ) ?>

            </div>

        </section>

        <?php endforeach; ?>


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
<input type="hidden" name="wp_csrf_token" value="<?= esc($csrfToken) ?>">

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
<input type="hidden" name="wp_csrf_token" value="<?= esc($csrfToken) ?>">

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
<input type="hidden" name="wp_csrf_token" value="<?= esc($csrfToken) ?>">

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
<input type="hidden" name="wp_csrf_token" value="<?= esc($csrfToken) ?>">

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
<input type="hidden" name="wp_csrf_token" value="<?= esc($csrfToken) ?>">

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
<input type="hidden" name="wp_csrf_token" value="<?= esc($csrfToken) ?>">

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
<input type="hidden" name="wp_csrf_token" value="<?= esc($csrfToken) ?>">

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
<input type="hidden" name="wp_csrf_token" value="<?= esc($csrfToken) ?>">

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

    <!-- Floating pagination is synchronized to the currently active tab by weaponpaints.js. -->
    <aside
        class="wp-floating-pagination"
        id="wpFloatingPagination"
        aria-label="Page navigation"
        hidden
    >
        <div class="wp-floating-pagination__heading">
            <i class="fas fa-list-ol" aria-hidden="true"></i>
            <span>PAGE</span>
        </div>
        <div class="wp-floating-pagination__info" id="wpFloatingPaginationInfo">1 / 1</div>
        <nav
            class="wp-floating-pagination__controls"
            id="wpFloatingPaginationControls"
            aria-label="Choose page"
        ></nav>
    </aside>

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
<input type="hidden" name="wp_csrf_token" value="<?= esc($csrfToken) ?>">

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

    currentPhpFile: <?= json_encode(currentScriptPath(), $jsonFlags) ?>,

    csrfToken: <?= json_encode($csrfToken, $jsonFlags) ?>
};
</script>
