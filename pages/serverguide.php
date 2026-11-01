<?php
/**
 * FragCity Server Guide.
 *
 * The command catalog is kept in data/server-guide.json so command entries can
 * be updated without turning this page into a long article or editing markup.
 */
$guideDataPath = dirname(__DIR__) . '/data/server-guide.json';
$guideData = [];

try {
    $guideRaw = is_file($guideDataPath) ? file_get_contents($guideDataPath) : false;
    $decodedGuide = is_string($guideRaw)
        ? json_decode($guideRaw, true, 64, JSON_THROW_ON_ERROR)
        : null;

    if (is_array($decodedGuide)) {
        $guideData = $decodedGuide;
    }
} catch (Throwable $guideError) {
    error_log('[FragCity Server Guide] Catalog could not be loaded: ' . get_class($guideError));
}

$guideEscape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$guideCategories = is_array($guideData['categories'] ?? null)
    ? $guideData['categories']
    : [];
$guideItems = is_array($guideData['items'] ?? null)
    ? $guideData['items']
    : [];
$guideMeta = is_array($guideData['meta'] ?? null)
    ? $guideData['meta']
    : [];

$guideCategoryMap = [];
$guideCategoryCounts = [];
foreach ($guideCategories as $category) {
    if (!is_array($category) || empty($category['id'])) {
        continue;
    }
    $categoryId = (string) $category['id'];
    $guideCategoryMap[$categoryId] = $category;
    $guideCategoryCounts[$categoryId] = 0;
}

foreach ($guideItems as $item) {
    if (!is_array($item)) {
        continue;
    }
    $categoryId = (string) ($item['category'] ?? '');
    if (isset($guideCategoryCounts[$categoryId])) {
        ++$guideCategoryCounts[$categoryId];
    }
}

$guideCommandCount = count($guideItems);
$guideCategoryCount = count($guideCategoryMap);
?>

<section class="server-guide" id="fragcityServerGuide" dir="rtl">
    <div class="sg-shell">
        <header class="sg-hero">
            <div class="sg-hero-copy">
                <div class="sg-eyebrow">
                    <span class="sg-eyebrow-dot"></span>
                    FRAGCITY <span>/</span> SERVER FIELD GUIDE
                </div>
                <h1>
                    راهنمای <span>سرور</span>
                </h1>
                <p>
                    دستورها، مودها و تنظیمات سرور CS2 را بر اساس پلاگین پیدا کن؛
                    Permission و وضعیت فعال بودن را ببین و با یک کلیک کپی کن.
                </p>
                <div class="sg-hero-meta">
                    <span><i class="fa-solid fa-terminal" aria-hidden="true"></i> <?= number_format($guideCommandCount) ?> مورد راهنما</span>
                    <span><i class="fa-solid fa-layer-group" aria-hidden="true"></i> <?= number_format($guideCategoryCount) ?> دسته‌بندی</span>
                    <span><i class="fa-solid fa-copy" aria-hidden="true"></i> کپی سریع</span>
                </div>
            </div>
            <div class="sg-hero-art" aria-hidden="true">
                <span class="sg-art-orbit sg-art-orbit-one"></span>
                <span class="sg-art-orbit sg-art-orbit-two"></span>
                <span class="sg-art-crosshair"><i class="fa-solid fa-crosshairs"></i></span>
                <span class="sg-art-terminal">
                    <span><i class="fa-solid fa-circle"></i> <i class="fa-solid fa-circle"></i> <i class="fa-solid fa-circle"></i></span>
                    <code><b>&gt;</b> !settings</code>
                    <code><b>&gt;</b> .ready</code>
                    <code><b>&gt;</b> !modes</code>
                    <small>FRAGCITY // CS2</small>
                </span>
            </div>
        </header>

        <section class="sg-directory" aria-label="فهرست دستورات، مودها و تنظیمات">
            <div class="sg-toolbar">
                <label class="sg-search" for="sgSearch">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input
                        id="sgSearch"
                        type="search"
                        autocomplete="off"
                        placeholder="جستجوی دستور، پلاگین، توضیح یا کلیدواژه؛ مثل !ban یا MatchZy"
                        aria-label="جستجوی دستورات و تنظیمات"
                    >
                    <kbd>CTRL K</kbd>
                </label>
                <button type="button" class="sg-clear-search" id="sgClearSearch" hidden>
                    پاک کردن جستجو
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <nav class="sg-category-nav" id="sgCategoryNav" aria-label="دسته‌بندی دستورات">
                <button type="button" class="sg-category-filter active" data-guide-filter="all" data-guide-label="همه دستورات" aria-pressed="true">
                    <i class="fa-solid fa-border-all" aria-hidden="true"></i>
                    همه
                    <span><?= number_format($guideCommandCount) ?></span>
                </button>
                <?php foreach ($guideCategories as $category): ?>
                    <?php
                    if (!is_array($category) || empty($category['id'])) {
                        continue;
                    }
                    $categoryId = (string) $category['id'];
                    $categoryIcon = (string) ($category['icon'] ?? 'fa-terminal');
                    ?>
                    <button
                        type="button"
                        class="sg-category-filter"
                        data-guide-filter="<?= $guideEscape($categoryId) ?>"
                        data-guide-label="<?= $guideEscape($category['title'] ?? $categoryId) ?>"
                        aria-pressed="false"
                    >
                        <i class="fa-solid <?= $guideEscape($categoryIcon) ?>" aria-hidden="true"></i>
                        <?= $guideEscape($category['title'] ?? $categoryId) ?>
                        <span><?= number_format($guideCategoryCounts[$categoryId] ?? 0) ?></span>
                    </button>
                <?php endforeach; ?>
            </nav>

            <div class="sg-results-heading">
                <div>
                    <span class="sg-section-kicker">COMMAND DIRECTORY</span>
                    <h2 id="sgResultsTitle">همه دستورات</h2>
                </div>
                <span class="sg-results-count" id="sgResultsCount"><?= number_format($guideCommandCount) ?> مورد</span>
            </div>

            <?php if (!empty($guideMeta['notice'])): ?>
                <div class="sg-notice">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span><?= $guideEscape($guideMeta['notice']) ?></span>
                </div>
            <?php endif; ?>

            <div class="sg-entry-grid" id="sgEntryGrid">
                <?php foreach ($guideItems as $item): ?>
                    <?php
                    if (!is_array($item)) {
                        continue;
                    }

                    $itemCategory = (string) ($item['category'] ?? '');
                    $category = $guideCategoryMap[$itemCategory] ?? [];
                    $command = trim((string) ($item['command'] ?? ''));
                    $aliases = is_array($item['aliases'] ?? null)
                        ? array_values(array_filter($item['aliases'], 'is_string'))
                        : [];
                    $searchParts = [
                        $item['title'] ?? '',
                        $command,
                        implode(' ', $aliases),
                        $item['description'] ?? '',
                        $item['access'] ?? '',
                        $item['plugin'] ?? '',
                        $item['availability_note'] ?? '',
                        implode(' ', is_array($item['tags'] ?? null) ? $item['tags'] : []),
                    ];
                    $searchText = implode(' ', array_filter($searchParts, 'is_string'));

                    // The catalog distinguishes executable commands, configuration
                    // references and game-mode entries; keep the rendered label explicit.
                    $itemTypeRaw = (string) ($item['type'] ?? 'command');
                    $itemType = in_array($itemTypeRaw, ['command', 'setting', 'mode'], true)
                        ? $itemTypeRaw
                        : 'command';
                    $itemTypeLabels = [
                        'command' => 'دستور',
                        'setting' => 'تنظیم',
                        'mode' => 'مود',
                    ];
                    $itemTypeLabel = $itemTypeLabels[$itemType];

                    // Status is catalog metadata, not a live check of the server.
                    $availability = (string) ($item['availability'] ?? '');
                    $availabilityLabels = [
                        'default' => 'در پکیج پیش‌فرض',
                        'config' => 'وابسته به تنظیمات',
                        'mode' => 'فقط در مود مربوط',
                        'verify' => 'نیازمند تست اجرا',
                        'disabled' => 'در کانفیگ غیرفعال',
                    ];
                    $availabilityLabel = $availabilityLabels[$availability] ?? '';
                    $availabilityClass = preg_replace('/[^a-z0-9_-]/i', '-', $availability);

                    // Only allow HTTPS documentation links; never render a raw HTML URL.
                    $sourceCandidate = trim((string) ($item['source_url'] ?? ''));
                    $sourceParts = $sourceCandidate !== '' ? parse_url($sourceCandidate) : false;
                    $sourceUrl = filter_var($sourceCandidate, FILTER_VALIDATE_URL)
                        && is_array($sourceParts)
                        && strtolower((string) ($sourceParts['scheme'] ?? '')) === 'https'
                        ? $sourceCandidate
                        : '';

                    $consoleCommand = trim((string) ($item['console_command'] ?? ''));
                    ?>
                    <article
                        class="sg-entry"
                        data-guide-entry
                        data-guide-category="<?= $guideEscape($itemCategory) ?>"
                        data-guide-search="<?= $guideEscape($searchText) ?>"
                    >
                        <div class="sg-entry-top">
                            <span class="sg-entry-category">
                                <i class="fa-solid <?= $guideEscape($category['icon'] ?? 'fa-terminal') ?>" aria-hidden="true"></i>
                                <?= $guideEscape($category['title'] ?? 'عمومی') ?>
                            </span>
                            <span class="sg-entry-type sg-entry-type--<?= $guideEscape($itemType) ?>">
                                <?= $guideEscape($itemTypeLabel) ?>
                            </span>
                        </div>

                        <div class="sg-entry-title-row">
                            <h3><?= $guideEscape($item['title'] ?? $command) ?></h3>
                            <span class="sg-entry-access"><?= $guideEscape($item['access'] ?? '—') ?></span>
                        </div>

                        <?php if (!empty($item['plugin']) || $availabilityLabel !== '' || $sourceUrl !== ''): ?>
                            <div class="sg-entry-meta">
                                <?php if (!empty($item['plugin'])): ?>
                                    <span class="sg-entry-plugin">
                                        <i class="fa-solid fa-puzzle-piece" aria-hidden="true"></i>
                                        <?= $guideEscape($item['plugin']) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($availabilityLabel !== ''): ?>
                                    <span class="sg-entry-availability sg-entry-availability--<?= $guideEscape($availabilityClass) ?>">
                                        <?= $guideEscape($availabilityLabel) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($sourceUrl !== ''): ?>
                                    <a class="sg-entry-source" href="<?= $guideEscape($sourceUrl) ?>" target="_blank" rel="noopener noreferrer">
                                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                        منبع و مستندات
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="sg-command-line">
                            <code dir="ltr"><?= $guideEscape($command) ?></code>
                            <button
                                type="button"
                                class="sg-copy-button"
                                data-guide-copy="<?= $guideEscape($command) ?>"
                                aria-label="کپی دستور <?= $guideEscape($command) ?>"
                                title="کپی دستور"
                            >
                                <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                <span>کپی</span>
                            </button>
                        </div>

                        <?php if ($consoleCommand !== '' && $consoleCommand !== $command): ?>
                            <div class="sg-command-line sg-command-line--console">
                                <span class="sg-command-label">کنسول سرور</span>
                                <code dir="ltr"><?= $guideEscape($consoleCommand) ?></code>
                                <button
                                    type="button"
                                    class="sg-copy-button"
                                    data-guide-copy="<?= $guideEscape($consoleCommand) ?>"
                                    aria-label="کپی دستور کنسول <?= $guideEscape($consoleCommand) ?>"
                                    title="کپی دستور کنسول"
                                >
                                    <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                    <span>کپی</span>
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php if ($aliases !== []): ?>
                            <div class="sg-aliases">
                                <span>نام‌های جایگزین</span>
                                <div>
                                    <?php foreach ($aliases as $alias): ?>
                                        <code dir="ltr"><?= $guideEscape($alias) ?></code>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <p class="sg-entry-description"><?= $guideEscape($item['description'] ?? '') ?></p>

                        <?php if (!empty($item['example'])): ?>
                            <div class="sg-example">
                                <span>مثال</span>
                                <code dir="ltr"><?= $guideEscape($item['example']) ?></code>
                                <button
                                    type="button"
                                    class="sg-example-copy"
                                    data-guide-copy="<?= $guideEscape($item['example']) ?>"
                                    aria-label="کپی مثال"
                                    title="کپی مثال"
                                >
                                    <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($item['note'])): ?>
                            <div class="sg-entry-note">
                                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                                <?= $guideEscape($item['note']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($item['availability_note'])): ?>
                            <div class="sg-entry-availability-note">
                                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                <?= $guideEscape($item['availability_note']) ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="sg-empty-state" id="sgEmptyState" hidden>
                <span><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                <strong>موردی پیدا نشد</strong>
                <p>عبارت جستجو یا دسته‌بندی انتخاب‌شده را تغییر بده.</p>
                <button type="button" id="sgResetFilters">نمایش همه دستورات</button>
            </div>

            <div class="sg-toast" id="sgToast" role="status" aria-live="polite"></div>
        </section>
    </div>
</section>
