@php
    $_tabbarLimit = 4;
    $_tabItems = navbar()->topLevel($_tabbarLimit);
    $_overflowItems = navbar()->overflow($_tabbarLimit);

    $_hasOverflow = count($_overflowItems) > 0;
    $_socials = footer()->socials()->all();
    $_availableLangs = (array) config('lang.available', []);
    $_hasLangs = count($_availableLangs) > 1;
    $_hasThemeSwitch = (bool) config('app.change_theme', true);
    $_authEnabled = (bool) config('app.auth_enabled', true);
    $_onlyModal = (bool) config('auth.only_modal');
    $_onlySocial = (bool) config('auth.only_social', false);
    $_singleSocial = $_onlySocial && sizeof(social()->getAll()) === 1;
    $_singleSocialKey = $_singleSocial ? key(social()->toDisplay()) : null;
    $_isMaintenance = (bool) config('app.maintenance_mode');
    $_showAuth = $_authEnabled && !user()->isLoggedIn();

    $_hasMoreSecondary = $_hasOverflow || $_hasLangs || $_hasThemeSwitch || !empty($_socials) || $_showAuth;

    $_renderTabbar = count($_tabItems) > 0 || $_hasMoreSecondary;
@endphp

@if ($_renderTabbar)
    <nav class="tabbar" id="tabbar" data-tabbar
        data-overflow-count="{{ count($_overflowItems) }}"
        hx-boost="true" hx-target="#main" hx-swap="outerHTML transition:true"
        aria-label="{{ __('def.menu') }}">
        <div class="tabbar__indicator" data-tabbar-indicator aria-hidden="true"></div>
        <div class="tabbar__content">
            @foreach ($_tabItems as $item)
                @if (count($item['children']) === 0)
                    <x-header.tabbar.link :item="$item" />
                @else
                    <x-header.tabbar.dropdown :item="$item" />
                @endif
            @endforeach

            @if ($_hasMoreSecondary)
                <button type="button" class="tabbar__item tabbar__item--more"
                    data-modal-open="tabbar-more"
                    aria-haspopup="dialog"
                    aria-controls="tabbar-more"
                    aria-label="{{ __('def.more') }}">
                    <span class="tabbar__item-icon">
                        <x-icon path="ph.regular.dots-three-outline" />
                    </span>
                    <span class="tabbar__item-label">{{ __('def.more') }}</span>
                </button>
            @endif
        </div>
    </nav>

    @if ($_hasMoreSecondary)
        @push('footer')
            <x-modal id="tabbar-more" title="{{ __('def.menu') }}">
                <div class="tabbar-sheet" hx-boost="true" hx-target="#main" hx-swap="outerHTML transition:true">
                    @if ($_showAuth)
                        <section class="tabbar-sheet__section">
                            <div class="tabbar-sheet__auth">
                                @if ($_singleSocial)
                                    <a class="tabbar-sheet__auth-primary" href="{{ url('social/' . $_singleSocialKey) }}" hx-boost="false">
                                        <x-icon path="ph.regular.sign-in" />
                                        <span>@t('auth.social.auth_via', [':social' => $_singleSocialKey])</span>
                                    </a>
                                @elseif ($_onlyModal)
                                    <button type="button" class="tabbar-sheet__auth-primary" data-modal-open="auth-modal">
                                        <x-icon path="ph.regular.sign-in" />
                                        <span>{{ __('def.login') }}</span>
                                    </button>
                                    @if (!$_isMaintenance)
                                        <button type="button" class="tabbar-sheet__auth-secondary" data-modal-open="register-modal">
                                            {{ __('def.register') }}
                                        </button>
                                    @endif
                                @else
                                    <a class="tabbar-sheet__auth-primary" href="{{ url('login') }}">
                                        <x-icon path="ph.regular.sign-in" />
                                        <span>{{ __('def.login') }}</span>
                                    </a>
                                    @if (!$_isMaintenance)
                                        <a class="tabbar-sheet__auth-secondary" href="{{ url('register') }}">
                                            {{ __('def.register') }}
                                        </a>
                                    @endif
                                @endif
                            </div>
                        </section>
                    @endif

                    @if ($_hasOverflow)
                        <section class="tabbar-sheet__section">
                            <h3 class="tabbar-sheet__section-title">{{ __('def.menu') }}</h3>
                            <div class="tabbar-sheet__items">
                                <x-header.tabbar.dropdown-children :children="$_overflowItems" :level="0" />
                            </div>
                        </section>
                    @endif

                    @if ($_hasLangs || $_hasThemeSwitch)
                        <section class="tabbar-sheet__section tabbar-sheet__section--utility">
                            <h3 class="tabbar-sheet__section-title">{{ __('def.preferences') }}</h3>

                            @if ($_hasThemeSwitch)
                                <button type="button" class="tabbar-sheet__row tabbar-sheet__row--theme"
                                    data-tabbar-theme-toggle
                                    aria-label="{{ __('def.change_theme') }}">
                                    <span class="tabbar-sheet__row-icon">
                                        <x-icon path="ph.regular.sun" class="sun-icon" />
                                        <x-icon path="ph.regular.moon" class="moon-icon" />
                                    </span>
                                    <span class="tabbar-sheet__row-text">{{ __('def.change_theme') }}</span>
                                    <span class="tabbar-sheet__row-toggle" aria-hidden="true">
                                        <span class="tabbar-sheet__row-toggle-thumb"></span>
                                    </span>
                                </button>
                            @endif

                            @if ($_hasLangs)
                                <div class="tabbar-sheet__row tabbar-sheet__row--langs">
                                    <span class="tabbar-sheet__row-icon">
                                        <x-icon path="ph.regular.translate" />
                                    </span>
                                    <span class="tabbar-sheet__row-text">{{ __('def.language') }}</span>
                                    <div class="tabbar-sheet__langs" role="group" aria-label="{{ __('def.language') }}">
                                        @foreach ($_availableLangs as $lang)
                                            <a class="tabbar-sheet__lang {{ $lang === app()->getLang() ? 'is-active' : '' }}"
                                                href="{{ url()->addParams(['lang' => $lang]) }}"
                                                hreflang="{{ $lang }}" lang="{{ $lang }}"
                                                aria-current="{{ $lang === app()->getLang() ? 'page' : 'false' }}"
                                                hx-boost="false">
                                                <img src="{{ asset('assets/img/langs/' . $lang . '.svg') }}"
                                                    alt="{{ __('langs.' . $lang) }}" loading="lazy" width="18" height="18">
                                                <span>{{ strtoupper($lang) }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </section>
                    @endif

                    @if (!empty($_socials))
                        <section class="tabbar-sheet__section">
                            <h3 class="tabbar-sheet__section-title">{{ __('def.socials') }}</h3>
                            <div class="tabbar-sheet__socials">
                                @foreach ($_socials as $social)
                                    <a class="tabbar-sheet__social" href="{{ $social->url }}"
                                        target="_blank" rel="noopener noreferrer"
                                        aria-label="@t($social->name)">
                                        <x-icon path="{{ $social->icon }}" />
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>
            </x-modal>
        @endpush
    @endif
@endif
