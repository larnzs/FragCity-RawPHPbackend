<?php

use Flute\Core\Modules\Translation\Services\TranslationService;

if (!function_exists('__')) {
    /**
     * Get the translation for a given key.
     *
     * @param  string  $key
     * @param  array|string  $replacements
     * @param  string|null  $locale
     * @return string
     */
    function __(string $key, array|string $replacements = [], ?string $locale = null): string
    {
        static $translator = null;

        if ($translator === null) {
            $translator = translation();
        }

        if (is_string($replacements)) {
            $default = $replacements;
            $replacements = [];
        }

        $trans = $translator->trans($key, normalize_translation_replacements($replacements), $locale);

        if ($trans === $key && isset($default)) {
            return $default;
        }

        return $trans;
    }
}

if (!function_exists('t')) {
    /**
     * Get the translation for a given key.
     *
     * @param  string  $key
     * @param  array|string  $replacements
     * @param  string|null  $locale
     * @return string
     */
    function t(string $key, array|string $replacements = [], ?string $locale = null): string
    {
        return __($key, $replacements, $locale);
    }
}

if (!function_exists('trans')) {
    /**
     * Get the translation for a given key.
     * Alias of __ function.
     *
     * @param  string  $key
     * @param  array  $replacements
     * @param  string|null  $locale
     * @return string
     */
    function trans(string $key, array $replacements = [], ?string $locale = null): string
    {
        return __($key, $replacements, $locale);
    }
}

if (!function_exists('normalize_translation_replacements')) {
    function normalize_translation_replacements(array $replacements): array
    {
        foreach ($replacements as $key => $value) {
            if ($value === null || is_scalar($value)) {
                $replacements[$key] = $value;

                continue;
            }

            if ($value instanceof \Stringable) {
                $replacements[$key] = (string) $value;

                continue;
            }

            if (is_object($value)) {
                $replacement = null;

                foreach (['name', 'title', 'key', 'code', 'value', 'id'] as $property) {
                    if (isset($value->{$property}) && is_scalar($value->{$property})) {
                        $replacement = (string) $value->{$property};

                        break;
                    }
                }

                $replacements[$key] =
                    $replacement ?? ( method_exists($value, 'getName') ? (string) $value->getName() : $value::class );

                continue;
            }

            $replacements[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return $replacements;
    }
}

if (!function_exists('trans_choice')) {
    function trans_choice(string $key, int|float $number, array $replacements = [], ?string $locale = null): string
    {
        $replacements['count'] = $number;
        $line = __($key, $replacements, $locale);

        if (str_contains($line, '|')) {
            $parts = explode('|', $line);
            $line = $number == 1 ? $parts[0] ?? $line : ( end($parts) ?: $line );
        }

        return str_replace([':count', '%count%'], (string) $number, $line);
    }
}

if (!function_exists('transValue')) {
    /**
     * Resolve a translatable value (JSON or plain string) for the current locale.
     *
     * Accepts either a plain string (returned as-is for backward compat)
     * or a JSON-encoded object / PHP array keyed by locale codes, e.g.
     *   {"ru":"Главная","en":"Home"}
     *
     * Fallback chain: requested locale → site default locale → first available → ''
     *
     * @param  mixed        $value   Raw DB value (string, array, or null)
     * @param  string|null  $locale  Override locale (null = current)
     * @return string
     */
    function transValue(mixed $value, ?string $locale = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Plain string that is NOT JSON
        if (is_string($value)) {
            if ($value[0] !== '{') {
                // If looks like a translation key (e.g. "def.home"), try __() for backward compat
                if (str_contains($value, '.') && !str_contains($value, ' ')) {
                    $translated = __($value, [], $locale);
                    if ($translated !== $value) {
                        return $translated;
                    }
                }

                return $value;
            }

            $decoded = json_decode($value, true);

            if (!is_array($decoded)) {
                return $value;
            }

            $value = $decoded;
        }

        if (!is_array($value)) {
            return (string) $value;
        }

        // Empty array
        if (empty($value)) {
            return '';
        }

        $locale ??= app()->getLang();
        $defaultLocale = config('lang.locale', 'en');

        return $value[$locale] ?? $value[$defaultLocale] ?? reset($value) ?: '';
    }
}

if (!function_exists('translation')) {
    /**
     * Get the translation service instance.
     *
     * @return TranslationService
     */
    function translation(): TranslationService
    {
        static $service = null;

        if ($service === null) {
            $service = app(TranslationService::class);
        }

        return $service;
    }
}
