@props(['item'])

@php
    $_active = collect($item['children'])->contains(fn($c) => trim(active($c['url'])) !== '');
@endphp

<button type="button" class="tabbar__item tabbar__item--dropdown {{ $_active ? 'active' : '' }}"
    data-modal-open="tabbar-{{ $item['id'] }}"
    aria-haspopup="dialog"
    aria-controls="tabbar-{{ $item['id'] }}"
    aria-label="{{ transValue($item['title']) }}">
    <span class="tabbar__item-icon">
        @if ($item['icon'])
            <x-icon path="{{ $item['icon'] }}" />
        @else
            <x-icon path="ph.regular.dots-three" />
        @endif
    </span>
    <span class="tabbar__item-label">{{ transValue($item['title']) }}</span>
</button>

@push('footer')
    <x-modal id="tabbar-{{ $item['id'] }}" title="{{ transValue($item['title']) }}">
        <div class="tabbar-sheet" hx-boost="true" hx-target="#main" hx-swap="outerHTML transition:true">
            <section class="tabbar-sheet__section">
                <div class="tabbar-sheet__items">
                    <x-header.tabbar.dropdown-children :children="$item['children']" :level="0" />
                </div>
            </section>
        </div>
    </x-modal>
@endpush
