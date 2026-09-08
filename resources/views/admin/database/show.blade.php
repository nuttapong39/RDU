@extends('layouts.admin')

@section('title', 'HOSxP Database Configuration')

@section('content')
<div class="max-w-2xl">

    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">HOSxP Database Configuration</h1>
        <p class="text-gray-500 mt-1 text-sm">ตั้งค่าการเชื่อมต่อกับ HOSxP Hospital Database (Read-Only)</p>
    </div>

    {{-- Current Status --}}
    <div class="mb-6 p-4 rounded-xl border flex items-center gap-3
        {{ $hasConfig ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200' }}">
        @if($hasConfig)
            <div class="flex-shrink-0">
                <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div>
                <span class="text-green-800 font-semibold text-sm">มี Configuration แล้ว</span>
                <span class="text-green-600 text-sm ml-2">{{ $config['host'] }}:{{ $config['port'] }}/{{ $config['database'] }}</span>
            </div>
        @else
            <div class="flex-shrink-0">
                <svg class="w-5 h-5 text-amber-600 animate-pulse" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div>
                <span class="text-amber-800 font-semibold text-sm">ยังไม่มี Configuration</span>
                <span class="text-amber-600 text-sm ml-2">— ใส่ข้อมูลด้านล่างเพื่อเชื่อมต่อ HOSxP</span>
            </div>
        @endif
    </div>

    {{-- Config Form --}}
    <form id="configForm" method="POST" action="{{ route('admin.database.update') }}"
          class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">
        @csrf

        <div class="grid grid-cols-3 gap-4">
            {{-- Host --}}
            <div class="col-span-2">
                <label for="host" class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Host / IP Address <span class="text-red-500" aria-hidden="true">*</span>
                </label>
                <input type="text" id="host" name="host"
                       value="{{ old('host', $config['host'] ?? '') }}"
                       placeholder="192.168.1.100"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                              focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none
                              transition-colors duration-150
                              @error('host') border-red-400 bg-red-50 @enderror"
                       @error('host') aria-describedby="host-error" @enderror>
                @error('host')
                    <p id="host-error" class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Port --}}
            <div>
                <label for="port" class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Port <span class="text-red-500" aria-hidden="true">*</span>
                </label>
                <input type="number" id="port" name="port"
                       value="{{ old('port', $config['port'] ?? 5432) }}"
                       min="1" max="65535"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                              focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none
                              transition-colors duration-150
                              @error('port') border-red-400 bg-red-50 @enderror"
                       @error('port') aria-describedby="port-error" @enderror>
                @error('port')
                    <p id="port-error" class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            {{-- Database --}}
            <div>
                <label for="database" class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Database Name <span class="text-red-500" aria-hidden="true">*</span>
                </label>
                <input type="text" id="database" name="database"
                       value="{{ old('database', $config['database'] ?? 'hosxp') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                              focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none
                              transition-colors duration-150
                              @error('database') border-red-400 bg-red-50 @enderror"
                       @error('database') aria-describedby="database-error" @enderror>
                @error('database')
                    <p id="database-error" class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Schema --}}
            <div>
                <label for="schema" class="block text-sm font-semibold text-gray-700 mb-1.5">Schema</label>
                <input type="text" id="schema" name="schema"
                       value="{{ old('schema', $config['schema'] ?? 'public') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                              focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none
                              transition-colors duration-150">
            </div>
        </div>

        {{-- Username --}}
        <div>
            <label for="username" class="block text-sm font-semibold text-gray-700 mb-1.5">
                Username <span class="text-red-500" aria-hidden="true">*</span>
            </label>
            <input type="text" id="username" name="username" autocomplete="off"
                   value="{{ old('username', $config['username'] ?? 'rdu_readonly') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                          focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none
                          transition-colors duration-150
                          @error('username') border-red-400 bg-red-50 @enderror"
                   @error('username') aria-describedby="username-error" @enderror>
            @error('username')
                <p id="username-error" class="text-red-600 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password --}}
        <div>
            <label for="db-password" class="block text-sm font-semibold text-gray-700 mb-1.5">
                Password
                @if($hasConfig)
                    <span class="text-gray-400 font-normal text-xs ml-1">(เว้นว่างเพื่อใช้รหัสผ่านเดิม)</span>
                @else
                    <span class="text-red-500" aria-hidden="true">*</span>
                @endif
            </label>
            <div class="relative">
                <input type="password" id="db-password" name="password" autocomplete="new-password"
                       placeholder="{{ $hasConfig ? '••••••••' : 'ระบุรหัสผ่าน HOSxP' }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm
                              focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none
                              transition-colors duration-150
                              @error('password') border-red-400 bg-red-50 @enderror"
                       @error('password') aria-describedby="password-error" @enderror>
                <button type="button" id="toggleDbPassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600
                               transition-colors duration-150 cursor-pointer
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 rounded"
                        aria-label="แสดง/ซ่อนรหัสผ่าน">
                    <svg id="db-eye-show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg id="db-eye-hide" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                </button>
            </div>
            @error('password')
                <p id="password-error" class="text-red-600 text-xs mt-1">{{ $message }}</p>
            @enderror
            <p class="text-xs text-gray-400 mt-1">รหัสผ่านจะถูก Encrypt ก่อนบันทึก — ไม่มีการเก็บ Plain Text</p>
        </div>

        {{-- Test Result Area --}}
        <div id="testResult" class="hidden p-3 rounded-lg text-sm" role="status" aria-live="polite"></div>

        {{-- Action Buttons --}}
        <div class="flex items-center justify-between pt-3 border-t border-gray-100">
            <button type="button" id="btnTest"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 active:bg-gray-300
                           text-gray-700 rounded-lg text-sm font-medium transition-colors duration-150 cursor-pointer
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-1
                           disabled:opacity-50 disabled:cursor-not-allowed">
                <svg id="testSpinner" class="hidden animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <svg id="testIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                ทดสอบการเชื่อมต่อ
            </button>

            <button type="submit"
                    class="px-6 py-2 bg-blue-700 hover:bg-blue-800 active:bg-blue-900 text-white rounded-lg
                           text-sm font-semibold transition-colors duration-150 cursor-pointer
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1">
                บันทึก Configuration
            </button>
        </div>
    </form>

    {{-- Security Notice --}}
    <div class="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-500 space-y-2">
        <div class="flex items-start gap-2">
            <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <p><strong>ข้อควรระวัง:</strong> Connection นี้ใช้สิทธิ์ <code class="font-mono bg-gray-100 px-1 rounded">SELECT</code> เท่านั้น — ไม่สามารถแก้ไขข้อมูลใน HOSxP ได้</p>
        </div>
        <div class="flex items-start gap-2">
            <svg class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
            </svg>
            <p>Config ถูกเก็บใน <code class="font-mono bg-gray-100 px-1 rounded">storage/app/private/config/rdu_database.json</code> (ไม่อยู่ใน public directory)</p>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
    const toggleBtn = document.getElementById('toggleDbPassword');
    const pwdInput  = document.getElementById('db-password');
    const eyeShow   = document.getElementById('db-eye-show');
    const eyeHide   = document.getElementById('db-eye-hide');

    toggleBtn.addEventListener('click', function () {
        const isHidden = pwdInput.type === 'password';
        pwdInput.type  = isHidden ? 'text' : 'password';
        eyeShow.classList.toggle('hidden', isHidden);
        eyeHide.classList.toggle('hidden', !isHidden);
        toggleBtn.setAttribute('aria-label', isHidden ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
    });

    const okSvg   = `<svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>`;
    const failSvg = `<svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-9a1 1 0 012 0v4a1 1 0 01-2 0V9zm0-3a1 1 0 112 0 1 1 0 01-2 0z" clip-rule="evenodd"/></svg>`;

    document.getElementById('btnTest').addEventListener('click', async function () {
        const btn     = this;
        const spinner = document.getElementById('testSpinner');
        const icon    = document.getElementById('testIcon');
        const result  = document.getElementById('testResult');
        const form    = document.getElementById('configForm');

        spinner.classList.remove('hidden');
        icon.classList.add('hidden');
        btn.disabled = true;
        result.className = 'hidden p-3 rounded-lg text-sm';

        const data = {
            host:     form.host.value,
            port:     form.port.value,
            database: form.database.value,
            schema:   form.schema.value,
            username: form.username.value,
            password: form['password'].value,
        };

        try {
            const response = await fetch('{{ route('admin.database.test') }}', {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept':       'application/json',
                },
                body: JSON.stringify(data),
            });

            const json = await response.json();

            result.classList.remove('hidden');
            if (json.success) {
                result.className = 'p-3 rounded-lg text-sm bg-green-50 border border-green-200 text-green-800 flex items-center gap-2';
                result.innerHTML = okSvg +
                    `<span>${json.message}` +
                    (json.readonly_verified ? ' <strong class="ml-1">(Read-Only ยืนยันแล้ว)</strong>' : '') +
                    '</span>';
            } else {
                result.className = 'p-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800 flex items-center gap-2';
                result.innerHTML = failSvg + `<span>${json.message}</span>`;
            }
        } catch (e) {
            result.classList.remove('hidden');
            result.className = 'p-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800 flex items-center gap-2';
            result.innerHTML = failSvg + '<span>Network error — ไม่สามารถทดสอบได้</span>';
        }

        spinner.classList.add('hidden');
        icon.classList.remove('hidden');
        btn.disabled = false;
    });
})();
</script>
@endpush
