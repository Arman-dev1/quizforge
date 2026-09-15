<?php

namespace App\Services\Design;

use App\Models\Quiz;

/**
 * The appearance layer for a quiz. Design lives on `quiz->settings['design']`
 * (not versioned — restyling never needs a republish). This one class is the
 * single source of truth for defaults, the option catalogs, and the
 * design -> CSS-variable compilation used by BOTH the live preview and the
 * public player, so the two can never drift apart.
 */
class QuizDesign
{
    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'theme' => 'light',
            'primary' => '#0d9488',
            'secondary' => '#6366f1',
            'font_family' => 'inter',
            'font_size' => 'base',
            'button_style' => 'rounded',   // rounded | square | pill
            'border_radius' => 12,          // px
            'background_type' => 'color',   // color | gradient | image
            'background_color' => '#f5f7fa',
            'gradient_from' => '#e0f2fe',
            'gradient_to' => '#ede9fe',
            'background_image' => null,
            'card_style' => 'shadow',       // shadow | border | glass
            'progress_style' => 'bar',      // bar | dots | steps | hidden
            'input_style' => 'outline',     // outline | filled | underline
            'logo' => null,
            'cover' => null,
            'custom_css' => '',
            'custom_js' => '',
            'hide_branding' => false,
        ];
    }

    /**
     * Merge stored design over the defaults, dropping unknown keys so the
     * shape is always predictable.
     *
     * @return array<string, mixed>
     */
    public static function merge(?array $stored): array
    {
        $defaults = self::defaults();

        return array_merge($defaults, array_intersect_key((array) $stored, $defaults));
    }

    public static function forQuiz(Quiz $quiz): array
    {
        return self::merge(($quiz->settings ?? [])['design'] ?? []);
    }

    /**
     * Selectable themes: label + base surface/text tokens.
     *
     * `page` is the background the theme is meant to sit on. The quiz title
     * and description render *outside* the card, straight onto that
     * background, so a theme that only restyled the card left Dark with pale
     * text on a pale page — an invisible heading. Choosing a theme in the
     * editor therefore also moves the background (see `applyTheme`).
     */
    public static function themes(): array
    {
        return [
            'light' => ['label' => 'Light', 'text' => '#0f172a', 'muted' => '#64748b', 'card' => '#ffffff', 'card_border' => '#e5e7eb', 'page' => '#f5f7fa', 'gradient_from' => '#e0f2fe', 'gradient_to' => '#ede9fe'],
            'dark' => ['label' => 'Dark', 'text' => '#e5e7eb', 'muted' => '#94a3b8', 'card' => '#1e293b', 'card_border' => '#334155', 'page' => '#0f172a', 'gradient_from' => '#0f172a', 'gradient_to' => '#1e1b4b'],
            'minimal' => ['label' => 'Minimal', 'text' => '#111827', 'muted' => '#6b7280', 'card' => '#ffffff', 'card_border' => '#f1f5f9', 'page' => '#ffffff', 'gradient_from' => '#ffffff', 'gradient_to' => '#f8fafc'],
            'modern' => ['label' => 'Modern', 'text' => '#0b1324', 'muted' => '#5b6472', 'card' => '#ffffff', 'card_border' => '#e2e8f0', 'page' => '#eef2f7', 'gradient_from' => '#dbeafe', 'gradient_to' => '#f3e8ff'],
        ];
    }

    /**
     * Switch a design to a theme, carrying the background with it so the
     * preset is coherent on its own. Only the surface colours move — the
     * author's primary colour, fonts, radius and layout choices are theirs.
     *
     * @param  array<string, mixed>  $design
     * @return array<string, mixed>
     */
    public static function applyTheme(array $design, string $theme): array
    {
        $design = self::merge($design);

        if (! isset(self::themes()[$theme])) {
            return $design;
        }

        $tokens = self::themes()[$theme];

        $design['theme'] = $theme;
        $design['background_color'] = $tokens['page'];
        $design['gradient_from'] = $tokens['gradient_from'];
        $design['gradient_to'] = $tokens['gradient_to'];

        return $design;
    }

    /** Font catalog: label + a CSS stack that degrades to system fonts. */
    public static function fonts(): array
    {
        return [
            'inter' => ['label' => 'Inter', 'stack' => "'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif"],
            'system' => ['label' => 'System', 'stack' => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"],
            'serif' => ['label' => 'Serif', 'stack' => "Georgia, 'Times New Roman', ui-serif, serif"],
            'rounded' => ['label' => 'Rounded', 'stack' => "'Nunito', ui-rounded, 'Segoe UI', system-ui, sans-serif"],
            'mono' => ['label' => 'Mono', 'stack' => "ui-monospace, 'SF Mono', 'Cascadia Code', Menlo, monospace"],
        ];
    }

    /** Body font sizes in px. */
    public static function sizes(): array
    {
        return ['sm' => 15, 'base' => 16, 'lg' => 18];
    }

    /**
     * The full option catalog handed to the editor (labels for selects and
     * to the JS preview so it never hard-codes strings).
     */
    public static function catalog(): array
    {
        return [
            'themes' => self::themes(),
            'fonts' => self::fonts(),
            'sizes' => self::sizes(),
            'buttonStyles' => ['rounded' => 'Rounded', 'square' => 'Square', 'pill' => 'Pill'],
            'cardStyles' => ['shadow' => 'Shadow', 'border' => 'Border', 'glass' => 'Glass'],
            'progressStyles' => ['bar' => 'Bar', 'dots' => 'Dots', 'steps' => 'Steps', 'hidden' => 'Hidden'],
            'inputStyles' => ['outline' => 'Outline', 'filled' => 'Filled', 'underline' => 'Underline'],
            'backgroundTypes' => ['color' => 'Solid color', 'gradient' => 'Gradient', 'image' => 'Image'],
        ];
    }

    /**
     * Compile a design array into the CSS custom properties that both the
     * preview and player consume. Mirrors the JS in the editor exactly.
     *
     * @return array<string, string>
     */
    public static function cssVariables(array $d): array
    {
        $d = self::merge($d);
        $theme = self::themes()[$d['theme']] ?? self::themes()['light'];
        $fonts = self::fonts();
        $sizes = self::sizes();

        $radius = max(0, min(40, (int) $d['border_radius']));
        $btnRadius = match ($d['button_style']) {
            'square' => '0px',
            'pill' => '999px',
            default => $radius.'px',
        };

        return [
            '--qf-primary' => self::hex($d['primary'], '#0d9488'),
            '--qf-primary-ink' => self::readableInk(self::hex($d['primary'], '#0d9488')),
            '--qf-secondary' => self::hex($d['secondary'], '#6366f1'),
            '--qf-text' => $theme['text'],
            '--qf-muted' => $theme['muted'],
            '--qf-card' => $theme['card'],
            '--qf-card-border' => $theme['card_border'],
            '--qf-radius' => $radius.'px',
            '--qf-btn-radius' => $btnRadius,
            '--qf-font' => $fonts[$d['font_family']]['stack'] ?? $fonts['inter']['stack'],
            '--qf-font-size' => ($sizes[$d['font_size']] ?? 16).'px',
        ];
    }

    /** The `background` shorthand value for the quiz surface. */
    public static function backgroundCss(array $d, ?string $imageUrl = null): string
    {
        $d = self::merge($d);

        return match ($d['background_type']) {
            'gradient' => 'linear-gradient(135deg, '.self::hex($d['gradient_from'], '#e0f2fe').' 0%, '.self::hex($d['gradient_to'], '#ede9fe').' 100%)',
            'image' => $imageUrl
                ? "center / cover no-repeat url('".$imageUrl."')"
                : self::hex($d['background_color'], '#f5f7fa'),
            default => self::hex($d['background_color'], '#f5f7fa'),
        };
    }

    /** Render the compiled variables as an inline style attribute value. */
    public static function styleAttribute(array $d): string
    {
        return collect(self::cssVariables($d))
            ->map(fn ($value, $name) => $name.':'.$value)
            ->implode(';');
    }

    private static function hex(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) ? $value : $fallback;
    }

    /** Black or white text for legibility on a solid background colour. */
    private static function readableInk(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) < 6) {
            return '#ffffff';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Relative luminance (sRGB, quick approximation).
        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;

        return $luminance > 0.6 ? '#0f172a' : '#ffffff';
    }
}
