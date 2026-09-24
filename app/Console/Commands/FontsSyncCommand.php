<?php

namespace App\Console\Commands;

use App\Services\FontService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FontsSyncCommand extends Command
{
    protected $signature = 'fonts:sync';

    protected $description = 'ดึงรายชื่อฟอนต์ทั้งหมดจาก Google Fonts มาเก็บเป็น catalog ในโปรเจกต์ (database/data/google-fonts.json)';

    public function handle(FontService $fonts): int
    {
        $response = Http::timeout(60)->get('https://fonts.google.com/metadata/fonts');
        $list     = $response->successful() ? ($response->json('familyMetadataList') ?? []) : [];

        if (count($list) < 100) {
            $this->error('ดึงรายชื่อฟอนต์จาก Google ไม่สำเร็จ (HTTP ' . $response->status() . ')');
            return self::FAILURE;
        }

        usort($list, fn ($a, $b) => ($a['popularity'] ?? PHP_INT_MAX) <=> ($b['popularity'] ?? PHP_INT_MAX));

        $slim = [];
        foreach ($list as $font) {
            $weights = [];
            foreach (array_keys($font['fonts'] ?? []) as $variant) {
                if (ctype_digit((string) $variant)) {
                    $weights[] = (int) $variant;
                }
            }
            sort($weights);
            if (empty($font['family']) || empty($weights)) {
                continue;
            }
            $slim[] = [
                'f' => $font['family'],
                'c' => $font['category'] ?? 'Sans Serif',
                't' => in_array('thai', $font['subsets'] ?? [], true) ? 1 : 0,
                'w' => $weights,
            ];
        }

        $path = $fonts->catalogPath();
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, json_encode(
            ['generated_at' => now()->toDateString(), 'fonts' => $slim],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));

        $thai = count(array_filter($slim, fn ($f) => $f['t'] === 1));
        $this->info('บันทึก catalog แล้ว: ' . count($slim) . " ฟอนต์ (รองรับภาษาไทย {$thai})");

        return self::SUCCESS;
    }
}
