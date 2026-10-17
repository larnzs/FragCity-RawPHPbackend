@php
    $createdKey = session()->get('admin.api_keys.created_key');

    if ($createdKey) {
        session()->remove('admin.api_keys.created_key');
    }
@endphp

@if ($createdKey)
    <div class="alert alert-warning api-key-created-alert">
        <div class="api-key-created-alert__body">
            <div class="api-key-created-alert__title">@lang('admin-apikey.created_alert.title')</div>
            <div class="api-key-created-alert__text">
                @lang('admin-apikey.created_alert.description', ['name' => $createdKey['name'] ?? ''])
            </div>
            <div class="api-key-created-alert__key">
                <code>{{ $createdKey['key'] ?? '' }}</code>
                <button type="button" class="btn btn-sm" data-copy="{{ $createdKey['key'] ?? '' }}" data-tooltip="{{ __('def.copy') }}">
                    <x-icon path="ph.bold.copy-bold" />
                </button>
            </div>
        </div>
    </div>
@endif
