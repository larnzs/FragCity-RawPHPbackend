<?php
declare(strict_types=1);

$fcSuccessModalConfig = is_array($fcSuccessModalConfig ?? null)
    ? $fcSuccessModalConfig
    : [];

$fcSuccessModalAttr = static function (string $key, string $default = '') use ($fcSuccessModalConfig): string {
    $value = $fcSuccessModalConfig[$key] ?? $default;
    if (!is_scalar($value)) {
        $value = $default;
    }

    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<div
    class="fc-success-modal"
    data-fc-success-modal
    data-auto-open="<?= !empty($fcSuccessModalConfig['auto_open']) ? '1' : '0' ?>"
    data-dismissible="<?= !array_key_exists('dismissible', $fcSuccessModalConfig) || !empty($fcSuccessModalConfig['dismissible']) ? '1' : '0' ?>"
    data-reload-on-close="<?= !empty($fcSuccessModalConfig['reload_on_close']) ? '1' : '0' ?>"
    data-kind="<?= $fcSuccessModalAttr('kind', 'success') ?>"
    data-title="<?= $fcSuccessModalAttr('title') ?>"
    data-message="<?= $fcSuccessModalAttr('message') ?>"
    data-tier="<?= $fcSuccessModalAttr('tier') ?>"
    data-duration="<?= $fcSuccessModalAttr('duration') ?>"
    data-expires="<?= $fcSuccessModalAttr('expires') ?>"
    data-amount="<?= $fcSuccessModalAttr('amount') ?>"
    data-amount-label="<?= $fcSuccessModalAttr('amount_label', 'مبلغ پرداختی') ?>"
    data-reference="<?= $fcSuccessModalAttr('reference') ?>"
    data-return-url="<?= $fcSuccessModalAttr('return_url') ?>"
    data-return-label="<?= $fcSuccessModalAttr('return_label', 'ادامه در FragCity') ?>"
    aria-hidden="true"
    hidden
>
    <div class="fc-success-backdrop" data-fc-success-backdrop></div>
    <section class="fc-success-dialog" role="dialog" aria-modal="true" aria-labelledby="fcSuccessTitle" aria-describedby="fcSuccessMessage" tabindex="-1">
        <button type="button" class="fc-success-close" data-fc-success-close aria-label="بستن پنجره">
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>

        <div class="fc-success-decoration" aria-hidden="true">
            <span></span><span></span><span></span><span></span>
            <span></span><span></span><span></span><span></span>
        </div>

        <div class="fc-success-icon-stage" aria-hidden="true">
            <span class="fc-success-icon-orbit fc-success-icon-orbit-one"></span>
            <span class="fc-success-icon-orbit fc-success-icon-orbit-two"></span>
            <span class="fc-success-icon-glow"></span>
            <span class="fc-success-icon-core"><i class="fas fa-check"></i></span>
            <span class="fc-success-icon-spark fc-success-spark-one">✦</span>
            <span class="fc-success-icon-spark fc-success-spark-two">✧</span>
        </div>

        <div class="fc-success-copy" dir="rtl">
            <span class="fc-success-eyebrow" data-fc-success-eyebrow>FRAGCITY · SUCCESS</span>
            <h2 id="fcSuccessTitle" data-fc-success-title>عملیات با موفقیت انجام شد</h2>
            <p id="fcSuccessMessage" data-fc-success-message>درخواست شما با موفقیت ثبت شد.</p>
        </div>

        <div class="fc-success-facts" data-fc-success-facts hidden>
            <div class="fc-success-fact" data-fc-success-fact="tier" hidden>
                <span>سطح عضویت</span><strong data-fc-success-value="tier"></strong>
            </div>
            <div class="fc-success-fact" data-fc-success-fact="duration" hidden>
                <span>مدت عضویت</span><strong data-fc-success-value="duration"></strong>
            </div>
            <div class="fc-success-fact" data-fc-success-fact="expires" hidden>
                <span>اعتبار تا</span><strong data-fc-success-value="expires"></strong>
            </div>
            <div class="fc-success-fact" data-fc-success-fact="amount" hidden>
                <span data-fc-success-label="amount">مبلغ پرداختی</span><strong data-fc-success-value="amount"></strong>
            </div>
            <div class="fc-success-fact fc-success-fact-reference" data-fc-success-fact="reference" hidden>
                <span>شماره پیگیری</span><strong data-fc-success-value="reference" dir="ltr"></strong>
            </div>
        </div>

        <div class="fc-success-actions">
            <button type="button" class="fc-success-primary" data-fc-success-action>
                <span data-fc-success-action-label>مشاهده وضعیت اشتراک</span>
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
            </button>
            <span class="fc-success-footnote"><i class="fas fa-shield-halved" aria-hidden="true"></i> FragCity · تجربه‌ای امن و مطمئن</span>
        </div>
    </section>
</div>
