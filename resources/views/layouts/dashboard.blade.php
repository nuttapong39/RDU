<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — RDU Dashboard</title>
    @include('partials.fonts')
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Sidebar state — set before render to avoid FOUC --}}
    <script>
        (function () {
            var stored = localStorage.getItem('rduSidebar');
            var isDesktop = window.innerWidth >= 992;
            var state = stored || (isDesktop ? 'open' : 'closed');
            document.documentElement.setAttribute('data-sidebar', state);
        })();
    </script>
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased overflow-x-hidden">

@php
    $userName   = auth()->user()?->name ?? 'Guest';
    $userInitial = strtoupper(mb_substr($userName, 0, 1));
    $userEmail   = auth()->user()?->email ?? '';
@endphp

{{-- ============ SIDEBAR OVERLAY (shown when open on mobile) ============ --}}
<div id="sidebarOverlay" onclick="rduToggleSidebar()" aria-hidden="true"></div>

{{-- ================== SIDEBAR ================== --}}
<aside id="rduSidebar" aria-label="เมนูหลัก">

        {{-- Brand Header --}}
        <div class="px-4 py-4 border-b border-slate-200 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 ring-1 ring-slate-200 bg-white p-0.5">
                    <img src="{{ asset('images/logo_ck.png') }}" alt="ตราสัญลักษณ์โรงพยาบาลเชียงกลาง" class="w-full h-full object-contain rounded-full">
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-bold text-slate-800 leading-snug">
                        RDU Dashboard
                        <span class="text-[.65rem] font-medium text-slate-400 ml-1">V.1.0</span>
                    </div>
                    <div class="text-slate-500 text-xs leading-tight mt-0.5">รพ.เชียงกลาง จ.น่าน</div>
                </div>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 px-3 py-3 overflow-y-auto sidebar-scroll text-sm" aria-label="เมนูหลัก">

            {{-- Section: Main --}}
            <div class="text-slate-500 text-[.7rem] font-semibold tracking-wide px-3 pt-1 pb-1.5">
                เมนูหลัก
            </div>

            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-2.5 px-3 py-2 rounded-lg transition-colors duration-150 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500
                      {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
               aria-current="{{ request()->routeIs('dashboard') ? 'page' : 'false' }}">
                <span class="msi text-[1.1rem]">dashboard</span>
                <span>ภาพรวม 13 ตัวชี้วัด</span>
            </a>

            {{-- KPI Groups --}}
            @if(isset($kpiByCategory))
                @foreach($categories as $catKey => $catLabel)
                    @if($kpiByCategory->has($catKey))
                        <div class="pt-4 pb-0.5">
                            <div class="text-slate-500 text-[.7rem] font-semibold tracking-wide px-3 mb-1">
                                {{ $catLabel }}
                            </div>
                            <div class="divide-y divide-slate-100 mx-1">
                            @foreach($kpiByCategory[$catKey] as $item)
                                @php
                                    $isActive = request()->routeIs('dashboard.kpi')
                                        && request()->route('code') === $item['indicator']->code;
                                    [$statusDot, $statusText] = match($item['status']) {
                                        'pass'    => ['bg-emerald-500', 'ผ่านเกณฑ์'],
                                        'warning' => ['bg-amber-500', 'ใกล้เป้าหมาย'],
                                        'fail'    => ['bg-red-500', 'ไม่ผ่านเกณฑ์'],
                                        default   => ['bg-slate-300', 'ไม่มีข้อมูล'],
                                    };
                                @endphp
                                <a href="{{ route('dashboard.kpi', $item['indicator']->code) }}"
                                   title="{{ $item['indicator']->code }} — {{ $item['indicator']->name }}"
                                   class="flex items-center gap-2.5 px-3 py-2 transition-colors duration-150 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500
                                          {{ $isActive ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                                   aria-current="{{ $isActive ? 'page' : 'false' }}">

                                    <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $statusDot }}" role="img" aria-label="{{ $statusText }}"></span>

                                    <span class="font-mono text-xs font-semibold flex-shrink-0">{{ $item['indicator']->code }}</span>
                                    <span class="text-xs truncate min-w-0">{{ $item['indicator']->name }}</span>
                                </a>
                            @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif

            {{-- Divider --}}
            @can('manage-settings')
            <div class="border-t border-slate-200 my-3 mx-1"></div>

            {{-- Section: Settings --}}
            <div class="text-slate-500 text-[.7rem] font-semibold tracking-wide px-3 pb-1.5">
                ตั้งค่าระบบ
            </div>

            <a href="{{ route('admin.database.show') }}"
               class="flex items-center gap-2.5 px-3 py-2 rounded-lg transition-colors duration-150 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500
                      {{ request()->routeIs('admin.database.*') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
               aria-current="{{ request()->routeIs('admin.database.*') ? 'page' : 'false' }}">
                <span class="msi text-[1.1rem]">storage</span>
                <span>ตั้งค่าฐานข้อมูล HOSxP</span>
            </a>
            @endcan
        </nav>

        {{-- User Section --}}
        <div class="px-3 py-3 border-t border-slate-200 flex-shrink-0">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-slate-600 hover:bg-red-50 hover:text-red-600 transition-colors duration-150 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                    <span class="msi text-[1.1rem]">logout</span>
                    ออกจากระบบ
                </button>
            </form>
        </div>
    </aside>

    {{-- ============ MAIN AREA ============ --}}
    <div id="rduMain">

        {{-- Topbar --}}
        <header class="bg-white border-b border-slate-200 px-4 lg:px-6 py-3 flex items-center gap-3 flex-shrink-0 sticky top-0 z-20">
            {{-- Sidebar Toggle (all breakpoints) --}}
            <button type="button"
                    class="w-9 h-9 rounded-lg text-slate-500 hover:bg-blue-50 hover:text-blue-600 flex items-center justify-center cursor-pointer transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                    onclick="rduToggleSidebar()"
                    aria-label="เปิด/ปิดเมนู"
                    aria-controls="rduSidebar">
                <span class="msi sidebar-toggle-icon">menu_open</span>
            </button>

            {{-- Breadcrumb --}}
            <nav class="hidden sm:flex items-center gap-1.5 text-sm text-slate-500 min-w-0 flex-1" aria-label="breadcrumb">
                <a href="{{ route('dashboard') }}" class="hover:text-blue-600 flex items-center gap-1 transition-colors flex-shrink-0">
                    <span class="msi text-[1rem]">home</span>
                    <span>หน้าหลัก</span>
                </a>
                <span class="msi text-slate-300 text-[.9rem] flex-shrink-0">chevron_right</span>
                <span class="text-slate-800 font-semibold truncate min-w-0" title="@yield('title', 'Dashboard')">@yield('title', 'Dashboard')</span>
            </nav>

            {{-- Right side --}}
            <div class="ml-auto flex items-center gap-1.5">
                <div class="hidden md:block text-right leading-tight pr-2">
                    <div id="tbDate" class="text-xs font-semibold text-blue-600">—</div>
                    <div id="tbTime" class="text-[.68rem] text-slate-500 tabular-nums">—</div>
                </div>
                <button type="button"
                        class="w-9 h-9 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-blue-600 flex items-center justify-center cursor-pointer transition-colors relative"
                        aria-label="การแจ้งเตือน" title="การแจ้งเตือน">
                    <span class="msi">notifications</span>
                </button>
                <div class="hidden sm:flex items-center gap-2 px-2 py-1 rounded-lg border border-transparent">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-emerald-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0 select-none">
                        {{ $userInitial }}
                    </div>
                    <div class="hidden md:block leading-tight">
                        <div class="text-xs font-semibold text-slate-800">{{ $userName }}</div>
                        <div class="text-[.68rem] text-slate-500 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
                            เจ้าหน้าที่ระบบ
                        </div>
                    </div>
                </div>
            </div>
        </header>

        {{-- HOSxP not configured: say so instead of letting every KPI silently read as "no data" --}}
        @unless(app(\App\Services\DatabaseConfigService::class)->hasConfig())
            <div class="mx-4 lg:mx-6 mt-3 p-3 bg-amber-50 border border-amber-200 text-amber-900 rounded-lg text-sm flex items-center gap-2" role="alert">
                <span class="msi text-amber-600" aria-hidden="true">link_off</span>
                <span>ยังไม่ได้เชื่อมต่อฐานข้อมูล HOSxP — ตัวชี้วัดทั้งหมดจะแสดงเป็น "ไม่มีข้อมูล" จนกว่าจะตั้งค่าการเชื่อมต่อ
                    @can('manage-settings')
                        <a href="{{ route('admin.database.show') }}" class="font-semibold underline">ตั้งค่าตอนนี้</a>
                    @else
                        (กรุณาแจ้งผู้ดูแลระบบ)
                    @endcan
                </span>
            </div>
        @endunless

        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="mx-4 lg:mx-6 mt-3 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm flex items-center gap-2" role="alert">
                <span class="msi text-emerald-500">check_circle</span>
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mx-4 lg:mx-6 mt-3 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm flex items-center gap-2" role="alert">
                <span class="msi text-red-500">error</span>
                {{ session('error') }}
            </div>
        @endif
        @if(session('info'))
            <div class="mx-4 lg:mx-6 mt-3 p-3 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg text-sm flex items-center gap-2" role="alert">
                <span class="msi text-blue-500">info</span>
                {{ session('info') }}
            </div>
        @endif
        @if($errors->any())
            <div class="mx-4 lg:mx-6 mt-3 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm flex items-start gap-2" role="alert">
                <span class="msi text-red-500">error</span>
                <div>
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Page Content --}}
        <main class="flex-1 px-4 lg:px-6 py-5">
            @yield('content')
        </main>
    </div>

<script>
/* Sidebar toggle — works on all breakpoints, persists to localStorage */
function rduToggleSidebar() {
    var html = document.documentElement;
    var next = html.getAttribute('data-sidebar') === 'open' ? 'closed' : 'open';
    html.setAttribute('data-sidebar', next);
    localStorage.setItem('rduSidebar', next);
}
function rduCloseSidebar() {
    document.documentElement.setAttribute('data-sidebar', 'closed');
    localStorage.setItem('rduSidebar', 'closed');
}
/* Close on Escape (helpful on mobile overlay) */
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && window.innerWidth < 992) rduCloseSidebar();
});
/* Auto-close on mobile when clicking a sidebar link */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#rduSidebar a').forEach(function (a) {
        a.addEventListener('click', function () {
            if (window.innerWidth < 992) rduCloseSidebar();
        });
    });
});

/* Live clock (Thai พ.ศ.) */
(function () {
    const dateEl = document.getElementById('tbDate'), timeEl = document.getElementById('tbTime');
    const days = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
    const months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    function tick() {
        const n = new Date();
        const d = n.getDate(), m = months[n.getMonth()], y = n.getFullYear() + 543;
        const hh = String(n.getHours()).padStart(2,'0'), mm = String(n.getMinutes()).padStart(2,'0'), ss = String(n.getSeconds()).padStart(2,'0');
        if (dateEl) dateEl.textContent = `${days[n.getDay()]} ${d} ${m} ${y}`;
        if (timeEl) timeEl.textContent = `${hh}:${mm}:${ss} น.`;
    }
    tick(); setInterval(tick, 1000);
})();
</script>

@stack('scripts')
</body>
</html>
