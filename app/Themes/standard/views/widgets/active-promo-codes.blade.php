<x-card class="promo-codes">
    <x-slot name="header">
        <div class="promo-codes__header">
            <h5>{{ __('widgets.active_promo_codes') }}</h5>
            @if (!empty($promoCodes))
                <span class="promo-codes__count">{{ count($promoCodes) }}</span>
            @endif
        </div>
    </x-slot>

    @if (!empty($promoCodes))
        <div class="promo-codes__list">
            @foreach ($promoCodes as $promoCode)
                <div class="promo-codes__item">
                    <span class="promo-codes__discount"
                        data-tooltip="{{ $promoCode->type === 'percentage' ? __('widgets.discount.percentage', ['value' => $promoCode->value]) : __('widgets.discount.amount', ['value' => $promoCode->value, 'currency' => config('lk.currency_view')]) }}">
                        @if ($promoCode->type === 'percentage')
                            -{{ $promoCode->value }}%
                        @else
                            +{{ $promoCode->value }}
                        @endif
                    </span>
                    <div class="promo-codes__info">
                        <span class="promo-codes__value">{{ $promoCode->code }}</span>
                        @if ($promoCode->expires_at)
                            <span class="promo-codes__expires"
                                data-tooltip="{{ __('widgets.expires') }}: {{ carbon($promoCode->expires_at)->format('d.m.Y H:i') }}">
                                {{ carbon($promoCode->expires_at)->diffForHumans() }}
                            </span>
                        @endif
                    </div>
                    <button type="button"
                        class="promo-codes__copy"
                        onclick="copyToClipboard({{ json_encode($promoCode->code, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}); notyf.success({{ json_encode(__('widgets.promo_code_copy_success'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }})"
                        data-tooltip="{{ __('def.copy') }}">
                        <x-icon path="ph.regular.copy" />
                    </button>
                </div>
            @endforeach
        </div>
    @else
        <div class="promo-codes__empty">
            <x-icon path="ph.regular.ticket" />
            <span>{{ __('widgets.no_promo_codes') }}</span>
        </div>
    @endif
</x-card>
