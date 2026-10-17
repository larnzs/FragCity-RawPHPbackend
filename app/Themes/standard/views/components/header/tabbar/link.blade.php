@props(['item'])

@php
    $_active = trim(active($item['url'])) !== '';
@endphp

<a href="{{ url($item['url']) }}"
    @if ($item['new_tab']) target="_blank" rel="noopener" @endif
    class="tabbar__item {{ $_active ? 'active' : '' }}"
    @if ($_active) aria-current="page" @endif
    itemprop="url">
    <span class="tabbar__item-icon">
        <x-icon path="{{ $item['icon'] }}" />
    </span>
    <span class="tabbar__item-label" itemprop="name">{{ transValue($item['title']) }}</span>
</a>
