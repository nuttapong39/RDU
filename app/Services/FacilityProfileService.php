<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The hospital this install belongs to (CONTEXT.md: ข้อมูลสถานบริการ, ADR 0004): name, province, 5-digit hospcode and
 * logo, kept in `settings`. Until a name is saved the app shows a neutral "RDU Dashboard" (no hospital named).
 */
class FacilityProfileService
{
    public const KEYS = ['facility.name', 'facility.short_name', 'facility.province_code', 'facility.hospcode', 'facility.logo'];

    public const LOGO_DIR = 'facility';

    public const LOGO_MIMES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    private ?array $profile = null;

    /**
     * @return array{
     *     configured: bool, name: string, shortName: string, provinceCode: string, provinceName: string, hospcode: string,
     *     logo: ?string, title: string, shortTitle: string, hasHospcode: bool
     * }
     */
    public function profile(): array
    {
        return $this->profile ??= $this->load();
    }

    public function isConfigured(): bool
    {
        return $this->profile()['configured'];
    }

    /** The HDC hospital code, or '' when none is set (the HDC comparison is then hidden). */
    public function hospcode(): string
    {
        return $this->profile()['hospcode'];
    }

    public function provinceCode(): string
    {
        return $this->profile()['provinceCode'];
    }

    /** @return array<string, string> code => name, 77 provinces */
    public static function provinces(): array
    {
        return require database_path('data/th-provinces.php');
    }

    /** District name for an HDC `areacode`, or null when this province's districts are not in the data file. */
    public static function districtName(string $areacode): ?string
    {
        return (require database_path('data/th-districts.php'))[substr($areacode, 0, 4)] ?? null;
    }

    /**
     * Save the profile. `$logo` is validated by the caller (type, size); the file is re-checked here by content.
     *
     * @param  array{name: string, short_name: string, province_code: string, hospcode: ?string}  $data
     * @throws \InvalidArgumentException when the logo is not a PNG/JPEG/WebP image
     */
    public function save(array $data, ?UploadedFile $logo = null): void
    {
        $logoPath = $logo ? $this->storeLogo($logo) : null;

        Setting::putValue('facility.name', trim($data['name']));
        Setting::putValue('facility.short_name', trim($data['short_name']));
        Setting::putValue('facility.province_code', $data['province_code']);
        // putValue skips null, so an emptied hospcode is stored as an empty string
        Setting::putValue('facility.hospcode', (string) ($data['hospcode'] ?? ''));
        if ($logoPath !== null) {
            Setting::putValue('facility.logo', $logoPath);
        }

        $this->flush();
    }

    /** Copy an existing image file in as the logo (used by the one-time import). */
    public function importLogoFile(string $absolutePath): void
    {
        $this->setLogoFromContents((string) file_get_contents($absolutePath));
    }

    /** Where the stored logo lives on the private disk, or null when none (or the file is gone). */
    public function logoPath(): ?string
    {
        $path = $this->profile()['logo'];

        return $path !== null && Storage::disk('local')->exists($path) ? $path : null;
    }

    public function flush(): void
    {
        $this->profile = null;
    }

    private function load(): array
    {
        $values = [];
        try {
            $values = Setting::whereIn('key', self::KEYS)->pluck('value', 'key')->all();
        } catch (Throwable $e) {
            // The page must still render if the settings table cannot be read (first install, DB down).
            Log::warning('Facility profile unreadable', ['error' => get_class($e)]);
        }

        $name         = trim((string) ($values['facility.name'] ?? ''));
        $short        = trim((string) ($values['facility.short_name'] ?? ''));
        $provinceCode = (string) ($values['facility.province_code'] ?? '');
        $province     = self::provinces()[$provinceCode] ?? '';
        $hospcode     = preg_match('/^\d{5}$/', (string) ($values['facility.hospcode'] ?? '')) ? $values['facility.hospcode'] : '';
        $configured   = $name !== '';

        return [
            'configured'   => $configured,
            'name'         => $name,
            'shortName'    => $short !== '' ? $short : $name,
            'provinceCode' => $provinceCode,
            'provinceName' => $province,
            'hospcode'     => $hospcode,
            'hasHospcode'  => $hospcode !== '',
            'logo'         => ($values['facility.logo'] ?? '') !== '' ? $values['facility.logo'] : null,
            'title'        => $configured ? trim($name.' '.$this->provincePhrase($provinceCode, $province, false)) : 'RDU Dashboard',
            'shortTitle'   => $configured ? trim(($short !== '' ? $short : $name).' '.$this->provincePhrase($provinceCode, $province, true)) : '',
        ];
    }

    /** "จังหวัดน่าน" / "จ.น่าน"; Bangkok is not a "จังหวัด". */
    private function provincePhrase(string $code, string $province, bool $short): string
    {
        if ($province === '') {
            return '';
        }

        return $code === '10' ? $province : ($short ? 'จ.' : 'จังหวัด').$province;
    }

    private function storeLogo(UploadedFile $file): string
    {
        return $this->setLogoFromContents((string) file_get_contents($file->getRealPath()), persist: false);
    }

    /** Check the bytes really are PNG/JPEG/WebP (not trusting the name or declared type), then keep them under a random name. */
    private function setLogoFromContents(string $bytes, bool $persist = true): string
    {
        $info = @getimagesizefromstring($bytes);
        $ext  = $info !== false ? (self::LOGO_MIMES[$info['mime']] ?? null) : null;

        if ($ext === null) {
            throw new \InvalidArgumentException('Logo must be a PNG, JPEG or WebP image.');
        }

        $path = self::LOGO_DIR.'/'.Str::random(40).'.'.$ext;
        Storage::disk('local')->put($path, $bytes);

        if ($persist) {
            Setting::putValue('facility.logo', $path);
            $this->flush();
        }

        return $path;
    }
}
