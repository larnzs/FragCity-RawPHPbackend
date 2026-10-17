@php
    $key = (string) $apiKey->key;
    $maskedKey = strlen($key) <= 12
        ? str_repeat('*', strlen($key))
        : substr($key, 0, 6) . str_repeat('*', max(strlen($key) - 10, 8)) . substr($key, -4);
@endphp

<div class="api-key">
    <span class="api-key__key">{{ $maskedKey }}</span>
</div>
