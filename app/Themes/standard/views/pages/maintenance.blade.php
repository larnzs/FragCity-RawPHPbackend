@extends('flute::layouts.error')

@section('title')
    {{ $title ?? __('def.maintenance_mode') }}
@endsection

@push('styles')
    <style>
        .mt-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 200px);
            padding: 2rem;
        }

        .mt-page__content {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            max-width: 480px;
        }

        .mt-page__icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: color-mix(in srgb, var(--accent) 10%, transparent);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: var(--accent);
            font-size: 1.5rem;
        }

        .mt-page__title {
            font-size: clamp(1.4rem, 4vw, 1.75rem);
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.02em;
            color: var(--text);
            margin: 0 0 0.75rem;
        }

        .mt-page__description {
            padding: 0.875rem 1rem;
            font-size: 0.875rem;
            line-height: 1.6;
            color: var(--text-200);
            margin: 0 0 1.5rem;
            text-align: center;
        }

        .mt-page__notice {
            backdrop-filter: blur(10px);
            border: 1px solid color-mix(in srgb, var(--text) 8%, transparent);
            width: 100%;
            padding: 0.875rem 1rem;
            border-radius: var(--border1);
            background: color-mix(in srgb, var(--text) 4%, transparent);
            border: 1px solid color-mix(in srgb, var(--text) 8%, transparent);
            margin-bottom: 2rem;
        }

        .mt-page__notice-label {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-200);
            margin-bottom: 6px;
        }

        .mt-page__notice-text {
            font-size: 0.875rem;
            line-height: 1.6;
            color: var(--text);
        }

        .mt-timer-wrap {
            width: 100%;
            margin-bottom: 2rem;
        }

        .mt-timer-wrap__heading {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-200);
            margin-bottom: 0.75rem;
        }

        .mt-timer {
            display: flex;
            justify-content: center;
            gap: 0.25rem;
            font-variant-numeric: tabular-nums;
        }

        .mt-timer__segment {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .mt-timer__value {
            font-size: clamp(2rem, 5vw, 2.75rem);
            font-weight: 800;
            line-height: 1;
            color: var(--text);
            min-width: 2.4ch;
        }

        .mt-timer__label {
            font-size: 0.65rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-200);
        }

        .mt-timer__colon {
            font-size: clamp(1.5rem, 4vw, 2rem);
            font-weight: 700;
            color: var(--text-200);
            opacity: 0.25;
            padding: 0 0.15rem;
            align-self: flex-start;
            margin-top: 0.15em;
        }

        .mt-page__actions {
            display: flex;
            gap: 0.75rem;
        }

        @media (max-width: 480px) {
            .mt-page__actions {
                flex-direction: column;
                width: 100%;
                max-width: 260px;
            }
        }
    </style>
@endpush

@push('content')
    <div class="mt-page">
        <div class="mt-page__content">
            <div class="mt-page__icon">
                <x-icon path="ph.bold.wrench-bold" />
            </div>

            <h1 class="mt-page__title">{{ $title ?? __('def.maintenance_mode') }}</h1>

            <p class="mt-page__description">{{ __('def.maintenance_description') }}</p>

            @if (!empty($message))
                <div class="mt-page__notice">
                    <div class="mt-page__notice-label">{{ __('def.maintenance_admin_message') }}</div>
                    <div class="mt-page__notice-text">{{ $message }}</div>
                </div>
            @endif

            @if (!empty($showTimer) && !empty($endTime))
                <div class="mt-timer-wrap">
                    <div class="mt-timer-wrap__heading">{{ __('def.maintenance_timer_heading') }}</div>
                    <div class="mt-timer" id="mt-timer" data-end="{{ $endTime }}">
                        <div class="mt-timer__segment">
                            <span class="mt-timer__value" id="mt-hours">--</span>
                            <span class="mt-timer__label">{{ __('def.timer_hours') }}</span>
                        </div>
                        <span class="mt-timer__colon">:</span>
                        <div class="mt-timer__segment">
                            <span class="mt-timer__value" id="mt-minutes">--</span>
                            <span class="mt-timer__label">{{ __('def.timer_minutes') }}</span>
                        </div>
                        <span class="mt-timer__colon">:</span>
                        <div class="mt-timer__segment">
                            <span class="mt-timer__value" id="mt-seconds">--</span>
                            <span class="mt-timer__label">{{ __('def.timer_seconds') }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-page__actions">
                <x-button onclick="location.reload()" type="accent" size="medium">
                    <x-icon path="ph.regular.arrow-counter-clockwise" />
                    {{ __('def.maintenance_try_again') }}
                </x-button>
            </div>
        </div>
    </div>

    @if (!empty($showTimer) && !empty($endTime))
        <script>
            (function() {
                var timer = document.getElementById('mt-timer');
                if (!timer || timer.dataset.init) return;
                timer.dataset.init = '1';

                var end = new Date(timer.dataset.end).getTime();
                var hEl = document.getElementById('mt-hours');
                var mEl = document.getElementById('mt-minutes');
                var sEl = document.getElementById('mt-seconds');

                function pad(n) { return n < 10 ? '0' + n : '' + n; }

                function tick() {
                    if (!document.body.contains(timer)) return;

                    var diff = Math.max(0, end - Date.now());

                    if (diff <= 0) {
                        hEl.textContent = '00';
                        mEl.textContent = '00';
                        sEl.textContent = '00';
                        setTimeout(function() { location.reload(); }, 3000);
                        return;
                    }

                    var s = Math.floor(diff / 1000);
                    hEl.textContent = pad(Math.floor(s / 3600));
                    mEl.textContent = pad(Math.floor((s % 3600) / 60));
                    sEl.textContent = pad(s % 60);

                    setTimeout(tick, 1000);
                }

                tick();
            })();
        </script>
    @endif
@endpush
