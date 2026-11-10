@extends('layouts.admin')

@section('title', 'ข้อมูลสถานบริการ')

@section('content')
<div class="max-w-3xl">
    <h1 class="text-xl font-bold text-gray-900 mb-1">ข้อมูลสถานบริการ</h1>
    <p class="text-sm text-gray-600 mb-5">
        ชื่อ จังหวัด รหัสหน่วยบริการ และโลโก้ของโรงพยาบาลนี้ ใช้แสดงในหน้าเข้าสู่ระบบ แถบด้านข้าง และหัวหน้าภาพรวม
        รหัสหน่วยบริการ 5 หลักใช้เทียบผลกับข้อมูลของโรงพยาบาลอื่นในจังหวัดจาก HDC (ถ้าไม่กรอก ระบบจะซ่อนการเทียบ HDC)
    </p>

    @unless($facility['configured'])
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="status">
            ยังไม่ได้ตั้งค่าข้อมูลสถานบริการ ระบบจึงแสดงเพียงชื่อ “RDU Dashboard” กรอกข้อมูลด้านล่างแล้วบันทึก
        </div>
    @endunless

    <form method="POST" action="{{ route('admin.facility.update') }}" enctype="multipart/form-data"
          class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 space-y-5" novalidate>
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">ชื่อสถานบริการ (เต็ม)</label>
            <input id="name" name="name" type="text" maxlength="120" required value="{{ old('name', $facility['name']) }}"
                   placeholder="เช่น โรงพยาบาลเชียงกลาง"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-400 @enderror">
            @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="short_name" class="block text-sm font-medium text-gray-700 mb-1">ชื่อย่อ</label>
                <input id="short_name" name="short_name" type="text" maxlength="40" required value="{{ old('short_name', $facility['shortName']) }}"
                       placeholder="เช่น รพ.เชียงกลาง"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('short_name') border-red-400 @enderror">
                @error('short_name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="province_code" class="block text-sm font-medium text-gray-700 mb-1">จังหวัด</label>
                <select id="province_code" name="province_code" required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 @error('province_code') border-red-400 @enderror">
                    <option value="">เลือกจังหวัด</option>
                    @foreach($provinces as $code => $label)
                        <option value="{{ $code }}" @selected(old('province_code', $facility['provinceCode']) === (string) $code)>{{ $label }} ({{ $code }})</option>
                    @endforeach
                </select>
                @error('province_code') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="hospcode" class="block text-sm font-medium text-gray-700 mb-1">รหัสหน่วยบริการ (5 หลัก)</label>
            <input id="hospcode" name="hospcode" type="text" inputmode="numeric" maxlength="5" pattern="\d{5}"
                   value="{{ old('hospcode', $facility['hospcode']) }}" placeholder="เช่น 11179"
                   class="w-40 rounded-md border border-gray-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 @error('hospcode') border-red-400 @enderror">
            @error('hospcode') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <span class="block text-sm font-medium text-gray-700 mb-1">โลโก้</span>
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full ring-1 ring-gray-200 bg-white p-1 flex items-center justify-center overflow-hidden flex-shrink-0">
                    <x-facility-logo :facility="$facility" class="w-full h-full object-contain rounded-full" />
                </div>
                <div class="min-w-0">
                    <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"
                           aria-describedby="logo-help"
                           class="block text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-600 file:px-3 file:py-1.5 file:text-white file:cursor-pointer hover:file:bg-blue-700">
                    <p id="logo-help" class="text-xs text-gray-500 mt-1">PNG, JPG หรือ WebP ขนาดไม่เกิน 1 MB (ไม่รับ SVG) — ไม่เลือกไฟล์ = ใช้โลโก้เดิม</p>
                </div>
            </div>
            @error('logo') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end pt-3 border-t border-gray-100">
            <button type="submit"
                    class="px-4 py-2 rounded-md bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                บันทึก
            </button>
        </div>
    </form>
</div>
@endsection
