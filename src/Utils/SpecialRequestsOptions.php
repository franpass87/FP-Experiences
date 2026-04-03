<?php

declare(strict_types=1);

namespace FP_Exp\Utils;

use function __;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_title;

/**
 * Opzioni checkbox per lo step «Richieste speciali» del widget (preset + personalizzate).
 */
final class SpecialRequestsOptions
{
    /**
     * ID preset nell'ordine storico del widget (valori inviati al carrello come parte della stringa).
     */
    public const PRESET_ORDER = [
        'vegano',
        'vegetariano',
        'celiaco',
        'allergie',
        'mobilita_ridotta',
        'gravidanza',
        'celebrazione',
    ];

    /**
     * Catalogo preset: gruppo UI + msgid italiano per __().
     *
     * @return array<string, array{group: string, label: string}>
     */
    public static function preset_catalog(): array
    {
        return [
            'vegano' => [
                'group' => 'food',
                'label' => __('Dieta vegana', 'fp-experiences'),
            ],
            'vegetariano' => [
                'group' => 'food',
                'label' => __('Dieta vegetariana', 'fp-experiences'),
            ],
            'celiaco' => [
                'group' => 'food',
                'label' => __('Celiaco / Intolleranza al glutine', 'fp-experiences'),
            ],
            'allergie' => [
                'group' => 'food',
                'label' => __('Allergie alimentari', 'fp-experiences'),
            ],
            'mobilita_ridotta' => [
                'group' => 'access',
                'label' => __('Mobilità ridotta / Accesso facilitato', 'fp-experiences'),
            ],
            'gravidanza' => [
                'group' => 'access',
                'label' => __('Gravidanza', 'fp-experiences'),
            ],
            'celebrazione' => [
                'group' => 'celebration',
                'label' => __('Compleanno / Anniversario / Evento speciale', 'fp-experiences'),
            ],
        ];
    }

    /**
     * Elenco predefinito: tutti i preset nell'ordine canonico.
     *
     * @return list<array{kind: 'preset', id: string}>
     */
    public static function default_widget_items(): array
    {
        $out = [];
        foreach (self::PRESET_ORDER as $id) {
            $out[] = ['kind' => 'preset', 'id' => $id];
        }

        return $out;
    }

    /**
     * Decodifica meta salvata; null = non configurato / legacy (usa default tutti i preset).
     *
     * @param mixed $stored Valore grezzo da post meta (string JSON o array).
     *
     * @return list<array{kind: 'preset', id: string}|array{kind: 'custom', slug: string, label: string}>|null
     */
    public static function parse_stored_meta(mixed $stored): ?array
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
        } elseif (is_array($stored)) {
            $decoded = $stored;
        } else {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $catalog = self::preset_catalog();
        $out = [];

        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }

            $kind = isset($row['kind']) ? sanitize_key((string) $row['kind']) : '';

            if ('preset' === $kind) {
                $id = sanitize_key((string) ($row['id'] ?? ''));
                if ($id !== '' && isset($catalog[$id])) {
                    $out[] = ['kind' => 'preset', 'id' => $id];
                }
            } elseif ('custom' === $kind) {
                $label = sanitize_text_field((string) ($row['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $slug = sanitize_key((string) ($row['slug'] ?? ''));
                if ($slug === '') {
                    $slug = self::slug_from_label($label);
                }
                $slug = substr($slug, 0, 60);
                if (isset($catalog[$slug]) || in_array($slug, self::PRESET_ORDER, true)) {
                    $slug = substr($slug . '_custom', 0, 60);
                }
                $out[] = ['kind' => 'custom', 'slug' => $slug, 'label' => $label];
            }
        }

        return $out;
    }

    /**
     * Risolve l'elenco da passare al template (null meta → tutti i preset).
     *
     * @return list<array{kind: 'preset', id: string}|array{kind: 'custom', slug: string, label: string}>
     */
    public static function resolve_items_for_widget(mixed $stored): array
    {
        $parsed = self::parse_stored_meta($stored);
        if ($parsed === null) {
            return self::default_widget_items();
        }

        return $parsed;
    }

    /**
     * True se l'array salvato equivale al pacchetto predefinito (nessuna personalizzazione reale).
     *
     * @param list<array{kind: 'preset', id: string}|array{kind: 'custom', slug: string, label: string}> $items
     */
    public static function is_equivalent_to_default(array $items): bool
    {
        $default = self::default_widget_items();
        if (count($items) !== count($default)) {
            return false;
        }

        foreach ($default as $i => $row) {
            if (! isset($items[$i]) || $items[$i] !== $row) {
                return false;
            }
        }

        return true;
    }

    public static function slug_from_label(string $label): string
    {
        $slug = sanitize_title($label);
        if ($slug === '') {
            $slug = 'opt_' . substr(md5($label), 0, 10);
        }

        return substr($slug, 0, 60);
    }

    /**
     * Titolo gruppo per il widget (msgid IT).
     */
    public static function group_title(string $groupKey): string
    {
        return match ($groupKey) {
            'food' => __('Richieste alimentari', 'fp-experiences'),
            'access' => __('Accessibilità', 'fp-experiences'),
            'celebration' => __('Celebrazioni', 'fp-experiences'),
            'custom' => __('Altre richieste', 'fp-experiences'),
            default => __('Opzioni', 'fp-experiences'),
        };
    }
}
