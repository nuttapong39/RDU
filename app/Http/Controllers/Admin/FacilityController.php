<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\FacilityProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin page for the hospital's name, province, hospcode and logo (CONTEXT.md: ข้อมูลสถานบริการ). */
class FacilityController extends Controller
{
    public function __construct(private FacilityProfileService $facility) {}

    public function show(): View
    {
        return view('admin.facility.index', ['provinces' => FacilityProfileService::provinces()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'short_name'    => ['required', 'string', 'max:40'],
            'province_code' => ['required', Rule::in(array_keys(FacilityProfileService::provinces()))],
            'hospcode'      => ['nullable', 'regex:/^\d{5}$/'],
            // PNG/JPEG/WebP only — never SVG, which can carry script. The bytes are checked again on save.
            'logo'          => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:max_width=4000,max_height=4000'],
        ], [
            'hospcode.regex' => 'รหัสหน่วยบริการต้องเป็นตัวเลข 5 หลัก',
            'logo.mimes'     => 'โลโก้ต้องเป็นไฟล์ PNG, JPG หรือ WebP',
            'logo.max'       => 'โลโก้ต้องมีขนาดไม่เกิน 1 MB',
        ]);

        $old = $this->facility->profile();

        try {
            $this->facility->save($data, $request->file('logo'));
        } catch (\InvalidArgumentException) {
            return back()->withInput()->withErrors(['logo' => 'ไฟล์นี้ไม่ใช่รูปภาพ PNG, JPG หรือ WebP']);
        }

        $new = $this->facility->profile();
        AuditLog::record('facility.update', [
            'subject'   => 'facility',
            'old_value' => ['name' => $old['name'], 'province' => $old['provinceCode'], 'hospcode' => $old['hospcode']],
            'new_value' => ['name' => $new['name'], 'province' => $new['provinceCode'], 'hospcode' => $new['hospcode'], 'logo_changed' => $request->hasFile('logo')],
        ]);

        return back()->with('success', 'บันทึกข้อมูลสถานบริการแล้ว');
    }
}
