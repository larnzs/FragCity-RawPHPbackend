<?php
$mcStorePricing = [
    'base_slots' => 5,
    'base_price' => 499000,
    'extra_slot_price' => 50000,
    'ram_prices' => [
        1024 => 0,
        1280 => 40000,
        1536 => 75000,
    ],
];
$mcStoreDefaultPlayers = 10;
?>
<main class="mc-store-page" dir="rtl">
    <div class="mc-store-shell">
        <section class="mc-store-hero">
            <div class="mc-store-hero__copy">
                <span class="mc-store-eyebrow"><i class="fa-solid fa-cubes" aria-hidden="true"></i> MINECRAFT HOSTING</span>
                <h1>سرور Minecraft خودت رو بساز</h1>
                <p>ظرفیت بازیکن و منابع مورد نیازت رو انتخاب کن و قیمت ماهانه رو لحظه‌ای ببین.</p>
            </div>
            <div class="mc-store-hero__mark" aria-hidden="true">
                <i class="fa-solid fa-cubes"></i>
                <span>MC</span>
            </div>
        </section>

        <div class="mc-store-layout">
            <section class="mc-store-panel mc-store-config" aria-labelledby="mcStoreConfigTitle">
                <div class="mc-store-panel__heading">
                    <div>
                        <span class="mc-store-kicker">SERVER CONFIGURATION</span>
                        <h2 id="mcStoreConfigTitle">پیکربندی سرور</h2>
                    </div>
                    <span class="mc-store-step">01 / 02</span>
                </div>

                <div
                    class="mc-store-pricing-data"
                    id="mcStorePricingData"
                    data-base-slots="<?= (int) $mcStorePricing['base_slots'] ?>"
                    data-base-price="<?= (int) $mcStorePricing['base_price'] ?>"
                    data-extra-slot-price="<?= (int) $mcStorePricing['extra_slot_price'] ?>"
                    data-ram-price-1024="<?= (int) $mcStorePricing['ram_prices'][1024] ?>"
                    data-ram-price-1280="<?= (int) $mcStorePricing['ram_prices'][1280] ?>"
                    data-ram-price-1536="<?= (int) $mcStorePricing['ram_prices'][1536] ?>"
                ></div>

                <div class="mc-store-field mc-store-field--capacity">
                    <div class="mc-store-field__top">
                        <label for="mcStorePlayers">ظرفیت بازیکن هم‌زمان</label>
                        <output id="mcStorePlayersValue" for="mcStorePlayers"><?= (int) $mcStoreDefaultPlayers ?></output>
                    </div>
                    <input
                        class="mc-store-range"
                        id="mcStorePlayers"
                        name="players"
                        type="range"
                        min="5"
                        max="64"
                        step="1"
                        value="<?= (int) $mcStoreDefaultPlayers ?>"
                        aria-describedby="mcStorePlayersHelp"
                    >
                    <div class="mc-store-range__limits"><span>۵ بازیکن</span><span>۶۴ بازیکن</span></div>
                    <small id="mcStorePlayersHelp">پلن پایه شامل ۵ اسلات است؛ هر اسلات بیشتر، ۵۰ هزار تومان به قیمت ماهانه اضافه می‌کند.</small>
                </div>

                <div class="mc-store-fields-grid">
                    <div class="mc-store-field">
                        <label for="mcStoreMemory">حافظه RAM</label>
                        <select class="mc-store-select" id="mcStoreMemory" name="memory">
                            <option value="1024" selected>۱ گیگابایت</option>
                            <option value="1280">۱.۲۵ گیگابایت</option>
                            <option value="1536">۱.۵ گیگابایت</option>
                        </select>
                        <small>ارتقای RAM به‌صورت جداگانه محاسبه می‌شود.</small>
                    </div>

                    <div class="mc-store-field">
                        <label for="mcStoreCpu">قدرت پردازنده</label>
                        <select class="mc-store-select" id="mcStoreCpu" name="cpu">
                            <option value="100" selected>۱ vCore · ۱۰۰٪</option>
                            <option value="200">۲ vCore · ۲۰۰٪</option>
                            <option value="300">۳ vCore · ۳۰۰٪</option>
                            <option value="400">۴ vCore · ۴۰۰٪</option>
                        </select>
                        <small>محدوده پردازنده از ۱ تا ۴ vCore.</small>
                    </div>

                    <div class="mc-store-field mc-store-field--wide">
                        <label for="mcStoreDisk">فضای ذخیره‌سازی SSD</label>
                        <select class="mc-store-select" id="mcStoreDisk" name="disk">
                            <option value="5120">۵ گیگابایت</option>
                            <option value="6144">۶ گیگابایت</option>
                            <option value="7168">۷ گیگابایت</option>
                            <option value="8192">۸ گیگابایت</option>
                            <option value="9216">۹ گیگابایت</option>
                            <option value="10240" selected>۱۰ گیگابایت</option>
                        </select>
                        <small>فضای SSD قابل انتخاب: ۵ تا ۱۰ گیگابایت.</small>
                    </div>
                </div>

                <div class="mc-store-info">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <p>مقدار CPU و SSD در محدوده‌ی انتخابی قابل تنظیم است. قیمت این صفحه بر اساس ظرفیت بازیکن و سطح RAM محاسبه می‌شود.</p>
                </div>
            </section>

            <aside class="mc-store-panel mc-store-summary" aria-labelledby="mcStoreSummaryTitle">
                <div class="mc-store-panel__heading">
                    <div>
                        <span class="mc-store-kicker">MONTHLY SUMMARY</span>
                        <h2 id="mcStoreSummaryTitle">خلاصه هزینه</h2>
                    </div>
                    <span class="mc-store-summary__icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
                </div>

                <div class="mc-store-summary__specs">
                    <div><span>ظرفیت</span><strong data-mc-summary="players">۱۰ بازیکن</strong></div>
                    <div><span>RAM</span><strong data-mc-summary="memory">۱ گیگابایت</strong></div>
                    <div><span>CPU</span><strong data-mc-summary="cpu">۱ vCore</strong></div>
                    <div><span>SSD</span><strong data-mc-summary="disk">۱۰ گیگابایت</strong></div>
                </div>

                <div class="mc-store-price-breakdown">
                    <div><span>پلن پایه · ۵ اسلات</span><strong data-mc-summary="base-price">۴۹۹٬۰۰۰ تومان</strong></div>
                    <div><span>اسلات‌های اضافه</span><strong data-mc-summary="extra-price">۵ × ۵۰٬۰۰۰ = ۲۵۰٬۰۰۰ تومان</strong></div>
                    <div><span>ارتقای RAM</span><strong data-mc-summary="ram-price">بدون هزینه اضافه</strong></div>
                </div>

                <div class="mc-store-total">
                    <span>هزینه ماهانه</span>
                    <strong data-mc-summary="total-price">۷۴۹٬۰۰۰ تومان</strong>
                    <small>قیمت نهایی بر اساس ظرفیت و RAM انتخاب‌شده محاسبه می‌شود.</small>
                </div>

                <div class="mc-store-traffic-note">
                    <i class="fa-solid fa-network-wired" aria-hidden="true"></i>
                    <p>هزینه ترافیک جداگانه به این مبلغ اضافه نمی‌شود و در تعرفه ظرفیت لحاظ شده است. مصرف واقعی به تعداد بازیکنان آنلاین، نوع دنیا و افزونه‌ها بستگی دارد.</p>
                </div>
            </aside>
        </div>
    </div>
</main>
