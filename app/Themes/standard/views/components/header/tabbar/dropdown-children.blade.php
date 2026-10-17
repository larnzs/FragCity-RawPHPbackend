@props(['children', 'level' => 0])

@foreach ($children as $child)
    @if (!empty($child['children']) && count($child['children']) > 0)
        <div class="tabbar-sheet__group" data-level="{{ $level }}">
            <button type="button" class="tabbar-sheet__item tabbar-sheet__group-trigger"
                aria-expanded="false">
                @if ($child['icon'])
                    <span class="tabbar-sheet__item-icon">
                        <x-icon path="{{ $child['icon'] }}" />
                    </span>
                @endif
                <span class="tabbar-sheet__item-text">
                    <span class="tabbar-sheet__item-title">{{ transValue($child['title']) }}</span>
                    @if (!empty($child['description']))
                        <span class="tabbar-sheet__item-desc">{{ transValue($child['description']) }}</span>
                    @endif
                </span>
                <span class="tabbar-sheet__group-chevron" aria-hidden="true">
                    <x-icon path="ph.bold.caret-down-bold" />
                </span>
            </button>
            <div class="tabbar-sheet__group-children" data-level="{{ $level + 1 }}">
                <x-header.tabbar.dropdown-children :children="$child['children']" :level="$level + 1" />
            </div>
        </div>
    @else
        @php
            $_active = trim(active($child['url'])) !== '';
        @endphp
        <a href="{{ url($child['url']) }}"
            @if ($child['new_tab']) target="_blank" rel="noopener" @endif
            class="tabbar-sheet__item {{ $_active ? 'is-active' : '' }}"
            @if ($_active) aria-current="page" @endif
            itemprop="url">
            @if ($child['icon'])
                <span class="tabbar-sheet__item-icon">
                    <x-icon path="{{ $child['icon'] }}" />
                </span>
            @endif
            <span class="tabbar-sheet__item-text">
                <span class="tabbar-sheet__item-title" itemprop="name">{{ transValue($child['title']) }}</span>
                @if (!empty($child['description']))
                    <span class="tabbar-sheet__item-desc">{{ transValue($child['description']) }}</span>
                @endif
            </span>
        </a>
    @endif
@endforeach
