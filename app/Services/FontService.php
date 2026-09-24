<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class FontService
{
    public const DEFAULT_SANS = 'Sarabun';
    public const DEFAULT_MONO = 'Fira Code';

    private const KEY_SANS = 'font_sans';
    private const KEY_MONO = 'font_mono';
    private const KEY_SCALE = 'font_scale';

    public const DEFAULT_SCALE = '100';
    /** Root font-size presets in percent — Tailwind's rem-based sizes/spacing scale with them. */
    public const SCALES = ['90' => 'เล็ก', '100' => 'ปกติ', '112.5' => 'ใหญ่', '125' => 'ใหญ่มาก'];
    private const WEIGHTS  = [300, 400, 500, 600, 700, 800];

    /** @var array<string, array{f:string,c:string,t:int,w:int[]}>|null */
    private ?array $catalog = null;

    public function catalogPath(): string
    {
        return database_path('data/google-fonts.json');
    }

    /** @return array<string, array{f:string,c:string,t:int,w:int[]}> keyed by family name */
    public function catalog(): array
    {
        if ($this->catalog === null) {
            $this->catalog = [];
            if (is_file($this->catalogPath())) {
                $data = json_decode((string) file_get_contents($this->catalogPath()), true);
                foreach ($data['fonts'] ?? [] as $font) {
                    $this->catalog[$font['f']] = $font;
                }
            }
        }

        return $this->catalog;
    }

    public function exists(string $family): bool
    {
        return isset($this->catalog()[$family]);
    }

    /** @return array{sans:string,mono:string} */
    public function current(): array
    {
        $rows = $this->settings();
        $sans = $rows[self::KEY_SANS] ?? null;
        $mono = $rows[self::KEY_MONO] ?? null;

        return [
            'sans' => $sans && $this->exists($sans) ? $sans : self::DEFAULT_SANS,
            'mono' => $mono && $this->exists($mono) ? $mono : self::DEFAULT_MONO,
        ];
    }

    public function isCustomized(): bool
    {
        $current = $this->current();

        return $current['sans'] !== self::DEFAULT_SANS || $current['mono'] !== self::DEFAULT_MONO;
    }

    /** Root font-size in percent, as a key of SCALES. */
    public function scale(): string
    {
        $value = $this->settings()[self::KEY_SCALE] ?? self::DEFAULT_SCALE;

        return array_key_exists($value, self::SCALES) ? $value : self::DEFAULT_SCALE;
    }

    public function save(string $sans, string $mono, string $scale = self::DEFAULT_SCALE): void
    {
        Setting::putValue(self::KEY_SANS, $sans, 'ฟอนต์หลักของระบบ (Google Fonts)');
        Setting::putValue(self::KEY_MONO, $mono, 'ฟอนต์ monospace ของระบบ (Google Fonts)');
        Setting::putValue(self::KEY_SCALE, $scale, 'ขนาดตัวอักษรของระบบ (% ของค่ามาตรฐาน)');
        Cache::forget('rdu.fonts.settings');
    }

    public function reset(): void
    {
        Setting::forgetKeys([self::KEY_SANS, self::KEY_MONO, self::KEY_SCALE]);
        Cache::forget('rdu.fonts.settings');
    }

    /** The stylesheet the layouts used before fonts became configurable (kept byte-identical). */
    public function defaultStylesheetUrl(): string
    {
        return 'https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&family=Fira+Code:wght@400;500;600&display=swap';
    }

    /** Google Fonts CSS2 URL for the given families, or null when none are known. */
    public function stylesheetUrl(array $families): ?string
    {
        $parts = [];
        foreach (array_unique($families) as $family) {
            $font = $this->catalog()[$family] ?? null;
            if (! $font) {
                continue;
            }
            $weights = array_values(array_intersect($font['w'], self::WEIGHTS)) ?: $font['w'];
            sort($weights);
            $parts[] = 'family=' . str_replace(' ', '+', $family) . ($weights ? ':wght@' . implode(';', $weights) : '');
        }

        return $parts ? 'https://fonts.googleapis.com/css2?' . implode('&', $parts) . '&display=swap' : null;
    }

    public function sansStack(string $family): string
    {
        return "'{$family}', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji'";
    }

    public function monoStack(string $family): string
    {
        return "'{$family}', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace";
    }

    /** @return array<string,string> */
    private function settings(): array
    {
        return Cache::remember('rdu.fonts.settings', 300, function () {
            try {
                return Setting::query()->whereIn('key', [self::KEY_SANS, self::KEY_MONO, self::KEY_SCALE])->pluck('value', 'key')->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }
}
