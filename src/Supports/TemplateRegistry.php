<?php

namespace Maina888\Whatsapp\Supports;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Registry of business-initiated message templates.
 *
 * Plugins register their templates through the `whatsapp_templates` filter:
 *
 *     add_filter('whatsapp_templates', function (array $templates) {
 *         $templates['order_shipped'] = [
 *             'label' => 'Order shipped',
 *             'template_name' => 'order_shipped',         // suggested Meta template name
 *             'language' => 'en',
 *             'variables' => ['customer_name', 'order_code', 'carrier', 'tracking_id'], // body {{1}}..{{n}}
 *             'button_variable' => null,                   // optional dynamic URL button suffix
 *             'preview' => 'Hi {{customer_name}}, order {{order_code}} is on its way with {{carrier}} ({{tracking_id}}).',
 *             'sample' => ['customer_name' => 'Demo Customer', ...],
 *             'group' => 'Shipping',
 *         ];
 *         return $templates;
 *     });
 *
 * The admin then maps every key to an approved Meta template (name + language) and enables it.
 */
class TemplateRegistry
{
    public const FILTER = 'whatsapp_templates';

    /**
     * @return array<string, array>
     */
    public static function all(): array
    {
        $templates = apply_filters(self::FILTER, []);

        $normalised = [];

        foreach ((array) $templates as $key => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $key = is_string($key) && ! is_numeric($key) ? $key : ($definition['key'] ?? null);

            if (! $key) {
                continue;
            }

            $normalised[$key] = static::normalise($key, $definition);
        }

        return $normalised;
    }

    public static function get(string $key): ?array
    {
        return static::all()[$key] ?? null;
    }

    public static function has(string $key): bool
    {
        return static::get($key) !== null;
    }

    public static function normalise(string $key, array $definition): array
    {
        $variables = array_values(array_filter(
            array_map(fn ($variable) => is_string($variable) ? trim($variable) : null, (array) ($definition['variables'] ?? [])),
            fn ($variable) => $variable !== null && $variable !== ''
        ));

        return [
            'key' => $key,
            'label' => $definition['label'] ?? Str::headline($key),
            'description' => $definition['description'] ?? null,
            'group' => $definition['group'] ?? 'General',
            'template_name' => $definition['template_name'] ?? $definition['template'] ?? $definition['meta_template'] ?? $key,
            'language' => $definition['language'] ?? null,
            'category' => strtoupper($definition['category'] ?? 'UTILITY'),
            'variables' => $variables,
            'button_variable' => $definition['button_variable'] ?? $definition['url_variable'] ?? null,
            'button_url' => $definition['button_url'] ?? null,
            'preview' => $definition['preview'] ?? $definition['default'] ?? $definition['default_text'] ?? null,
            'sample' => (array) ($definition['sample'] ?? []),
            'enabled' => (bool) ($definition['enabled'] ?? false),
        ];
    }

    /**
     * Order the caller's variables to match the definition's body placeholders.
     *
     * $vars may be associative (by variable name) or a positional list.
     *
     * @return array{body: string[], button: ?string}
     */
    public static function parameters(?array $definition, array $vars): array
    {
        $isList = array_is_list($vars);

        if (! $definition || ! $definition['variables']) {
            $body = $isList ? $vars : array_values(Arr::except($vars, [$definition['button_variable'] ?? '__none__']));

            return [
                'body' => array_map([static::class, 'cleanParameter'], $body),
                'button' => $definition && $definition['button_variable'] && ! $isList
                    ? static::cleanButton($vars[$definition['button_variable']] ?? null)
                    : null,
            ];
        }

        $body = [];

        foreach ($definition['variables'] as $index => $name) {
            $value = $isList ? ($vars[$index] ?? null) : ($vars[$name] ?? null);
            $body[] = static::cleanParameter($value);
        }

        $button = null;

        if ($definition['button_variable']) {
            $button = static::cleanButton(
                $isList ? ($vars[count($definition['variables'])] ?? null) : ($vars[$definition['button_variable']] ?? null)
            );
        }

        return ['body' => $body, 'button' => $button];
    }

    /**
     * Meta rejects empty parameters and parameters with new lines, tabs or more than 4 consecutive spaces.
     */
    public static function cleanParameter(mixed $value): string
    {
        if (is_bool($value)) {
            $value = $value ? 'yes' : 'no';
        }

        $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        if ($value === '') {
            return '-';
        }

        return Str::limit($value, 1000, '');
    }

    protected static function cleanButton(mixed $value): ?string
    {
        $value = trim((string) $value);

        // URL buttons take a suffix appended to the base URL approved at Meta
        // (e.g. https://example.com/{{1}}), so a full link to this site is reduced to its path.
        if ($value !== '' && function_exists('url')) {
            try {
                $base = rtrim((string) url('/'), '/') . '/';

                if (str_starts_with($value, $base)) {
                    $value = substr($value, strlen($base));
                }
            } catch (\Throwable) {
                // No application (pure unit tests): keep the value as-is.
            }
        }

        return $value === '' ? null : Str::limit($value, 2000, '');
    }

    /**
     * Render the human readable preview of a template with values (used for logging and SMS fallback).
     */
    public static function render(?array $definition, array $vars, array $body = []): string
    {
        if ($definition && $definition['preview']) {
            $text = $definition['preview'];

            foreach ($definition['variables'] as $index => $name) {
                $value = $body[$index] ?? (array_is_list($vars) ? ($vars[$index] ?? '') : ($vars[$name] ?? ''));
                $text = str_replace(
                    ['{{' . $name . '}}', '{{ ' . $name . ' }}', '{{' . ($index + 1) . '}}', '{' . $name . '}'],
                    (string) $value,
                    $text
                );
            }

            if ($definition['button_variable'] && ! array_is_list($vars) && isset($vars[$definition['button_variable']])) {
                $text = str_replace('{{' . $definition['button_variable'] . '}}', (string) $vars[$definition['button_variable']], $text);
            }

            return $text;
        }

        return implode(' | ', $body ?: array_map('strval', array_filter($vars, 'is_scalar')));
    }

    public static function placeholderPreview(array $definition): string
    {
        $text = (string) $definition['preview'];

        foreach ($definition['variables'] as $index => $name) {
            $text = str_replace(
                ['{{' . $name . '}}', '{{ ' . $name . ' }}', '{' . $name . '}'],
                '{{' . ($index + 1) . '}}',
                $text
            );
        }

        return $text;
    }
}
