<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — RDU Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&family=Fira+Code:wght@400;500;600&display=swap" rel="stylesheet">
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
    $catIcons = [
        'Antibiotic'     => 'medication',
        'DrugQuality'    => 'prescriptions',
        'PatientSafety'  => 'health_and_safety',
        'ChronicDisease' => 'bloodtype',
    ];
    $userName    = auth()->user()?->name ?? 'Guest';
    $userInitial = strtoupper(mb_substr($userName, 0, 1));
    $userEmail   = auth()->user()?->email ?? '';
@endphp

{{-- ============ SIDEBAR OVERLAY (shown when open on mobile) ============ --}}
<div id="sidebarOverlay" onclick="rduToggleSidebar()" aria-hidden="true"></div>

{{-- ================== SIDEBAR ================== --}}
<aside id="rduSidebar" aria-label="เมนูหลัก">

        {{-- Brand Header --}}
        <div class="px-4 py-4 border-b border-blue-800/40 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 shadow-sm bg-white p-0.5">
                    <img src="{{ asset('images/logo_ck.png') }}" alt="ตราสัญลักษณ์โรงพยาบาลเชียงกลาง" class="w-full h-full object-contain rounded-full">
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-bold text-white leading-snug">
                        RDU Dashboard
                        <span class="text-[.65rem] font-medium text-blue-300 ml-1">V.1.0</span>
                    </div>
                    <div class="text-blue-300 text-xs leading-tight mt-0.5">รพ.เชียงกลาง จ.น่าน</div>
                </div>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 px-3 py-3 overflow-y-auto sidebar-scroll text-sm" aria-label="เมนูหลัก">

            {{-- Section: Main --}}
            <div class="text-blue-200/90 text-[.68rem] font-semibold uppercase tracking-wider px-3 pt-1 pb-1.5">
                เมนูหลัก
            </div>

            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-2.5 px-3 py-2 rounded-lg transition-all duration-150 cursor-pointer
                      {{ request()->routeIs('dashboard') ? 'bg-blue-600 text-white shadow-sm' : 'text-blue-100 hover:bg-blue-800/60 hover:text-white' }}"
               aria-current="{{ request()->routeIs('dashboard') ? 'page' : 'false' }}">
                <span class="msi text-[1.1rem]">dashboard</span>
                <span>ภาพรวม 13 ตัวชี้วัด</span>
            </a>

            {{-- KPI Groups --}}
            @if(isset($kpiByCategory))
                @foreach($categories as $catKey => $catLabel)
                    @if($kpiByCategory->has($catKey))
                        <div class="pt-3 pb-0.5">
                            <div class="text-blue-200/90 text-[.68rem] font-semibold uppercase tracking-wider px-3 mb-1.5 flex items-center gap-1.5">
                                <span class="msi text-[.85rem] opacity-80">{{ $catIcons[$catKey] ?? 'category' }}</span>
                                {{ $catLabel }}
                            </div>
                            @foreach($kpiByCategory[$catKey] as $item)
                                @php
                                    $isActive = request()->routeIs('dashboard.kpi')
                                        && request()->route('code') === $item['indicator']->code;
                                    $statusIcon = match($item['status']) {
                                        'pass'    => ['check_circle', 'text-emerald-400'],
                                        'warning' => ['warning', 'text-amber-400'],
                                        'fail'    => ['cancel', 'text-red-400'],
                                        default   => ['remove', 'text-slate-400'],
                                    };
                                @endphp
                                <a href="{{ route('dashboard.kpi', $item['indicator']->code) }}"
                                   title="{{ $item['indicator']->name }}"
                                   class="flex items-start gap-2 px-3 py-1.5 rounded-lg transition-all duration-150 cursor-pointer
                                          {{ $isActive ? 'bg-blue-600 text-white shadow-sm' : 'text-blue-100 hover:bg-blue-800/60 hover:text-white' }}"
                                   aria-current="{{ $isActive ? 'page' : 'false' }}">

                                    <span class="msi text-[.85rem] flex-shrink-0 mt-0.5 {{ $statusIcon[1] }}">{{ $statusIcon[0] }}</span>

                                    <div class="min-w-0 flex-1">
                                        <div class="font-mono text-xs font-semibold leading-tight">{{ $item['indicator']->code }}</div>
                                        <div class="text-xs opacity-95 leading-snug break-words">{{ $item['indicator']->name }}</div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            @endif

            {{-- Divider --}}
            <div class="border-t border-blue-800/30 my-3 mx-1"></div>

            {{-- Section: Settings --}}
            <div class="text-blue-200/90 text-[.68rem] font-semibold uppercase tracking-wider px-3 pb-1.5">
                ตั้งค่าระบบ
            </div>

            <a href="{{ route('admin.database.show') }}"
               class="flex items-center gap-2.5 px-3 py-2 rounded-lg transition-all duration-150 cursor-pointer
                      {{ request()->routeIs('admin.database.*') ? 'bg-blue-600 text-white shadow-sm' : 'text-blue-100 hover:bg-blue-800/60 hover:text-white' }}"
               aria-current="{{ request()->routeIs('admin.database.*') ? 'page' : 'false' }}">
                <span class="msi text-[1.1rem]">storage</span>
                <span>ตั้งค่าฐานข้อมูล HOSxP</span>
            </a>
        </nav>

        {{-- User Section --}}
        <div class="px-4 py-3 border-t border-blue-800/40 flex-shrink-0">
            <div class="flex items-center gap-2.5 mb-2.5">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-emerald-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0 select-none"
                     aria-hidden="true">
                    {{ $userInitial }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-white text-xs font-medium truncate flex items-center gap-1">
                        {{ $userName }}
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block" aria-label="ออนไลน์"></span>
                    </div>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="flex items-center gap-1.5 text-xs text-blue-200 hover:text-red-300 transition-colors duration-150 cursor-pointer py-0.5">
                    <span class="msi text-[.9rem]">logout</span>
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
