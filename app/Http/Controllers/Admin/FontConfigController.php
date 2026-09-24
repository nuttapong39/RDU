<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\FontService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FontConfigController extends Controller
{
    public function __construct(private FontService $fonts) {}

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'font_sans'  => ['required', 'string', fn ($a, $v, $fail) => $this->fonts->exists($v) ?: $fail('ไม่พบฟอนต์หลักที่เลือกใน Google Fonts')],
            'font_mono'  => ['required', 'string', fn ($a, $v, $fail) => $this->fonts->exists($v) ?: $fail('ไม่พบฟอนต์ monospace ที่เลือกใน Google Fonts')],
            'font_scale' => ['required', Rule::in(array_map('strval', array_keys(FontService::SCALES)))],
        ]);

        $old = $this->snapshot();
        $this->fonts->save($data['font_sans'], $data['font_mono'], (string) $data['font_scale']);

        AuditLog::record('font_config.update', [
            'subject'   => 'System Fonts',
            'old_value' => $old,
            'new_value' => ['sans' => $data['font_sans'], 'mono' => $data['font_mono'], 'scale' => (string) $data['font_scale']],
        ]);

        return redirect()->to(route('admin.database.show') . '#fonts')
            ->with('success', 'บันทึกฟอนต์ของระบบเรียบร้อยแล้ว');
    }

    public function reset(): RedirectResponse
    {
        $old = $this->snapshot();
        $this->fonts->reset();

        AuditLog::record('font_config.update', [
            'subject'   => 'System Fonts (reset to default)',
            'old_value' => $old,
            'new_value' => ['sans' => FontService::DEFAULT_SANS, 'mono' => FontService::DEFAULT_MONO, 'scale' => FontService::DEFAULT_SCALE],
        ]);

        return redirect()->to(route('admin.database.show') . '#fonts')
            ->with('success', 'คืนค่าฟอนต์เริ่มต้นเรียบร้อยแล้ว');
    }

    private function snapshot(): array
    {
        return $this->fonts->current() + ['scale' => $this->fonts->scale()];
    }
}
