@extends('layouts.dashboard')

@section('title', 'ภาพรวม '.$kpiData->count().' ตัวชี้วัด RDU')

@section('content')

@php
    $userName    = auth()->user()?->name ?? 'ผู้ใช้งาน';
    $hour        = (int) now()->format('H');
    $greeting    = $hour < 12 ? 'สวัสดีตอนเช้า' : ($hour < 17 ? 'สวัสดีตอนบ่าย' : 'สวัสดีตอนเย็น');
    $userInitial = strtoupper(mb_substr($userName, 0, 1));

    $catMeta = [
        'Antibiotic'     => ['label' => 'ยาปฏิชีวนะ',   'icon' => 'medication',        'thumb' => 'dash-thumb-antibiotic', 'catBadge' => 'cat-badge-antibiotic', 'accent' => 'text-red-600',     'chip' => 'bg-red-100 text-red-600'],
        'DrugQuality'    => ['label' => 'คุณภาพยา',    'icon' => 'prescriptions',      'thumb' => 'dash-thumb-drug',        'catBadge' => 'cat-badge-drug',       'accent' => 'text-emerald-600', 'chip' => 'bg-emerald-100 text-emerald-600'],
        'PatientSafety'  => ['label' => 'ความปลอดภัย', 'icon' => 'health_and_safety',  'thumb' => 'dash-thumb-safety',      'catBadge' => 'cat-badge-safety',     'accent' => 'text-indigo-600',  'chip' => 'bg-indigo-100 text-indigo-600'],
        'ChronicDisease' => ['label' => 'โรคเรื้อรัง', 'icon' => 'bloodtype',          'thumb' => 'dash-thumb-chronic',     'catBadge' => 'cat-badge-chronic',    'accent' => 'text-cyan-700',    'chip' => 'bg-cyan-100 text-cyan-700'],
    ];

    $summaryItems = [
        'pass'    => ['label' => 'ผ่านเกณฑ์',    'icon' => 'check_circle', 'chip' => 'status-pass',    'text' => 'text-emerald-600'],
        'warning' => ['label' => 'ใกล้เป้าหมาย', 'icon' => 'warning',      'chip' => 'status-warning', 'text' => 'text-amber-600'],
        'fail'    => ['label' => 'ไม่ผ่านเกณฑ์', 'icon' => 'cancel',       'chip' => 'status-fail',    'text' => 'text-red-600'],
        'na'      => ['label' => 'ไม่มีข้อมูล',   'icon' => 'remove',       'chip' => 'status-na',      'text' => 'text-slate-500'],
    ];
@endphp

{{-- ============ WELCOME BANNER ============ --}}
<div class="welcome-banner mb-5 flex flex-wrap items-center justify-between gap-4">
    {{-- Big pill watermark (RDU = Rational Drug Use) --}}
    <span class="msi absolute pointer-events-none select-none"
          aria-hidden="true"
          style="right: -12px; top: 50%; transform: translateY(-50%) rotate(-18deg); font-size: 190px; opacity: .09; color: #fff; z-index: 0; line-height: 1;">
        pill
    </span>

    <div class="flex items-center gap-4 relative z-10">
        <div class="wb-avatar">{{ $userInitial }}</div>
        <div>
            <div class="text-[.85rem] opacity-90 flex items-center gap-1.5">
                <span class="msi text-[1rem]">waving_hand</span>
                {{ $greeting }}
            </div>
            <div class="text-xl md:text-2xl font-bold leading-tight">{{ $userName }}</div>
            <div class="wb-badge">
                <span class="msi text-[.85rem]">verified_user</span>
                RDU Dashboard — โรงพยาบาลเชียงกลาง
            </div>
        </div>
    </div>
    <div class="text-right relative z-10">
        <div class="text-[.75rem] opacity-80 flex items-center justify-end gap-1.5">
            <span class="msi text-[.9rem]">calendar_month</span>
            ปีงบประมาณ (พ.ศ.)
        </div>
        <div class="text-3xl font-bold tabular-nums leading-tight">{{ $year }}</div>
        <div class="text-[.72rem] opacity-80 mt-1 flex items-center justify-end gap-1.5">
            <span class="msi text-[.85rem]">update</span>
            อัปเดตล่าสุด {{ now()->locale('th')->translatedFormat('d M') }} {{ now()->year + 543 }}
        </div>
    </div>
</div>

{{-- ============ FILTER TOOLBAR ============ --}}
<form method="GET" action="{{ route('dashboard') }}"
      class="bg-white rounded-xl border border-slate-200 px-4 py-2.5 mb-5 flex flex-wrap items-center gap-3 shadow-sm">
    <span class="msi text-blue-600">filter_list</span>

    <div class="flex items-center gap-2">
        <label for="filter-year" class="text-xs text-slate-500 font-medium whitespace-nowrap">ปีงบประมาณ</label>
        <select id="filter-year" name="year"
                class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none cursor-pointer bg-white">
            @foreach($yearOptions as $y)
                <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex items-center gap-2">
        <label for="filter-month" class="text-xs text-slate-500 font-medium">เดือน</label>
        <select id="filter-month" name="month"
                class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none cursor-pointer bg-white">
            <option value="0" @selected($month === 0)>ทั้งปี</option>
            @foreach($monthOptions as $num => $name)
                <option value="{{ $num }}" @selected($num === $month)>{{ $name }}</option>
            @endforeach
        </select>
    </div>

    <button type="submit"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                   text-white rounded-lg text-sm font-medium transition-colors cursor-pointer
                   focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1">
        <span class="msi text-[1rem]">refresh</span>
        รีเฟรช
    </button>

    <div class="ml-auto flex items-center gap-1.5 text-xs text-slate-400">
        <span class="msi text-[.9rem]">history</span>
        คำนวณจาก HOSxP · โหมด Read-Only
    </div>
</form>

{{-- HDC-backed KPIs (no HOSxP query yet) only have a whole-year figure, so a month filter can't narrow them. --}}
@if($month !== 0 && $kpiByCategory->flatten(1)->contains(fn ($i) => ($i['source'] ?? 'hosxp') === 'hdc'))
    <div class="-mt-3 mb-6 flex items-start gap-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
        <span class="msi text-[1rem]">info</span>
        ตัวชี้วัดที่มีป้าย "HDC · ทั้งปี" แสดงยอดรวมทั้งปีงบประมาณเสมอ ไม่เปลี่ยนตามเดือนที่เลือก
        (ยังไม่มี query จาก HOSxP และ HDC ไม่มีข้อมูลรายเดือน)
    </div>
@endif

{{-- ============ STATUS SUMMARY BAR ============ --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    @foreach($summaryItems as $key => $cfg)
        <div class="bg-white rounded-xl border border-slate-200 px-4 py-3 flex items-center gap-3 shadow-sm">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0
                        {{ $key === 'pass' ? 'bg-emerald-50 text-emerald-600'
                           : ($key === 'warning' ? 'bg-amber-50 text-amber-600'
                           : ($key === 'fail' ? 'bg-red-50 text-red-600' : 'bg-slate-100 text-slate-500')) }}">
                <span class="msi text-xl">{{ $cfg['icon'] }}</span>
            </div>
            <div>
                <div class="text-2xl font-bold tabular-nums leading-none {{ $cfg['text'] }}">{{ $summary[$key] }}</div>
                <div class="text-xs text-slate-500 mt-1">{{ $cfg['label'] }}</div>
            </div>
        </div>
    @endforeach
</div>

{{-- ============ CATEGORY SUMMARY CARDS ============ --}}
<div class="section-head">
    <span class="msi">grid_view</span>
    สรุปตามหมวดตัวชี้วัด
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach($categories as $catKey => $catLabel)
        @php
            $stat = $categoryStats[$catKey] ?? null;
            $meta = $catMeta[$catKey] ?? ['icon' => 'category', 'thumb' => 'dash-thumb-drug', 'accent' => 'text-slate-600'];
        @endphp
        <a href="#cat-{{ Str::slug($catKey) }}" class="dash-card">
            <div class="dash-thumb {{ $meta['thumb'] }}"><span class="msi">{{ $meta['icon'] }}</span></div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-bold text-slate-800 truncate">{{ $catLabel }}</div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span class="text-2xl font-bold {{ $meta['accent'] }} tabular-nums leading-none">
                        {{ $stat && $stat['avg_pct'] !== null ? number_format($stat['avg_pct'], 1) : '—' }}
                    </span>
                    <span class="text-xs text-slate-400">% เฉลี่ย</span>
                </div>
                <div class="mt-1.5 flex gap-2.5 text-[.7rem] text-slate-500 tabular-nums">
                    <span><span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1"></span><b class="text-slate-700">{{ $stat['pass'] ?? 0 }}</b> ผ่าน</span>
                    <span><span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500 mr-1"></span><b class="text-slate-700">{{ $stat['warning'] ?? 0 }}</b> ใกล้</span>
                    <span><span class="inline-block w-1.5 h-1.5 rounded-full bg-red-500 mr-1"></span><b class="text-slate-700">{{ $stat['fail'] ?? 0 }}</b> ไม่ผ่าน</span>
                </div>
            </div>
            <span class="msi text-slate-300">chevron_right</span>
        </a>
    @endforeach
</div>

{{-- ============ KPI GRID (grouped by category) ============ --}}
@foreach($categories as $catKey => $catLabel)
    @if($kpiByCategory->has($catKey))
        @php $meta = $catMeta[$catKey] ?? ['icon' => 'category', 'catBadge' => '']; @endphp
        <div id="cat-{{ Str::slug($catKey) }}" class="section-head">
            <span class="msi">{{ $meta['icon'] }}</span>
            หมวด{{ $catLabel }} ({{ count($kpiByCategory[$catKey]) }} ตัวชี้วัด)
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">
            @foreach($kpiByCategory[$catKey] as $item)
                @php
                    $ind    = $item['indicator'];
                    $pct    = $item['percentage'];
                    $status = $item['status'];
                    $pctColor = match($status) {
                        'pass'    => 'text-emerald-600',
                        'warning' => 'text-amber-600',
                        'fail'    => 'text-red-600',
                        default   => 'text-slate-400',
                    };
                    $barColor = match($status) {
                        'pass'    => 'bg-emerald-500',
                        'warning' => 'bg-amber-500',
                        'fail'    => 'bg-red-500',
                        default   => 'bg-slate-300',
                    };
                    $statusChip = match($status) {
                        'pass'    => ['label' => 'ผ่านเกณฑ์', 'chip' => 'status-pass',    'icon' => 'check_circle'],
                        'warning' => ['label' => 'ใกล้เป้า',   'chip' => 'status-warning', 'icon' => 'warning'],
                        'fail'    => ['label' => 'ไม่ผ่าน',   'chip' => 'status-fail',    'icon' => 'cancel'],
                        default   => ['label' => 'ไม่มีข้อมูล', 'chip' => 'status-na',      'icon' => 'remove'],
                    };
                @endphp

                <a href="{{ route('dashboard.kpi', $ind->code) }}"
                   class="kpi-card-v2 status-{{ $status }} relative overflow-hidden"
                   aria-label="{{ $ind->code }}: {{ $ind->name }} — {{ $statusChip['label'] }}"
                   title="หมวด{{ $meta['label'] }} · สถานะ: {{ $statusChip['label'] }}">

                    {{-- Decorative watermark icon (108px, low opacity) --}}
                    <span class="msi absolute pointer-events-none select-none text-slate-500"
                          aria-hidden="true"
                          style="right: -14px; top: -10px; font-size: 108px; opacity: 0.08; line-height: 1; z-index: 0;">
                        {{ $meta['icon'] }}
                    </span>

                    {{-- Header: code chip only --}}
                    <div class="relative flex items-center gap-1.5">
                        <span class="cat-badge {{ $meta['catBadge'] }}">{{ $ind->code }}</span>
                        @if(($item['source'] ?? 'hosxp') === 'hdc')
                            @if($item['hdc_error'] ?? false)
                                <span class="text-[.65rem] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-700"
                                      title="ยังไม่มี query HOSxP และดึงข้อมูลจาก HDC ไม่ได้ในขณะนี้">HDC ไม่ตอบสนอง</span>
                            @else
                                <span class="text-[.65rem] font-bold px-1.5 py-0.5 rounded bg-hdc/10 text-hdc-ink"
                                      title="ยังไม่มี query HOSxP — แสดงยอดรวมทั้งปีงบจาก HDC (ข้อมูลส่วนกลาง)">HDC · ทั้งปี</span>
                            @endif
                        @endif
                    </div>

                    {{-- Name --}}
                    <p class="text-[.78rem] text-slate-600 leading-snug line-clamp-2 min-h-[2.4rem]">{{ $ind->name }}</p>

                    {{-- Percentage + num/den --}}
                    <div class="flex items-baseline justify-between mt-1">
                        <span class="text-3xl font-bold tabular-nums leading-none {{ $pctColor }}">
                            {{ $pct !== null ? $pct . '%' : 'N/A' }}
                        </span>
                        <span class="text-[.7rem] text-slate-400 tabular-nums">
                            {{ number_format($item['numerator']) }} / {{ number_format($item['denominator']) }}
                        </span>
                    </div>

                    {{-- Progress bar --}}
                    @if($pct !== null)
                        <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                            <div class="h-1.5 rounded-full animate-progress {{ $barColor }}"
                                 style="width: {{ min($pct, 100) }}%"
                                 role="progressbar"
                                 aria-valuenow="{{ $pct }}"
                                 aria-valuemin="0"
                                 aria-valuemax="100"></div>
                        </div>
                    @else
                        <div class="h-1.5"></div>
                    @endif

                    <div class="text-[.7rem] text-slate-400 flex items-center justify-between">
                        <span>เป้า {{ $ind->targetLabel() }}</span>
                        <span class="text-blue-500 flex items-center gap-0.5 font-medium">
                            ดูรายละเอียด
                            <span class="msi text-[.85rem]">arrow_forward</span>
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endforeach

{{-- ============ OVERVIEW CHART ============ --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm mb-6 overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-200 flex items-center gap-2 font-semibold text-sm text-slate-800">
        <span class="msi text-blue-600">stacked_line_chart</span>
        เปรียบเทียบผลลัพธ์ vs เป้าหมาย (ปีงบประมาณ {{ $year }})
    </div>
    <div class="p-4">
        <div style="height: 340px;">
            <canvas id="overviewChart" aria-label="กราฟเปรียบเทียบผลลัพธ์ {{ $kpiData->count() }} ตัวชี้วัด" role="img"></canvas>
        </div>
    </div>
</div>

{{-- ============ SHORTCUTS ============ --}}
<div class="section-head">
    <span class="msi">bolt</span>
    เมนูลัด
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    <a href="{{ route('admin.database.show') }}" class="shortcut-card">
        <div class="sc-icon sc-slate"><span class="msi">storage</span></div>
        <div>
            <div class="text-sm font-bold text-slate-800">ตั้งค่า HOSxP</div>
            <div class="text-xs text-slate-500 mt-0.5">ตั้งค่าการเชื่อมต่อฐานข้อมูลโรงพยาบาล</div>
        </div>
    </a>
    <a href="#" class="shortcut-card">
        <div class="sc-icon sc-green"><span class="msi">summarize</span></div>
        <div>
            <div class="text-sm font-bold text-slate-800">ออกรายงาน</div>
            <div class="text-xs text-slate-500 mt-0.5">Export Excel · CSV · PDF ตามตัวชี้วัด</div>
        </div>
    </a>
    <a href="#" class="shortcut-card">
        <div class="sc-icon sc-indigo"><span class="msi">info</span></div>
        <div>
            <div class="text-sm font-bold text-slate-800">คู่มือการใช้งาน</div>
            <div class="text-xs text-slate-500 mt-0.5">ทำความเข้าใจตัวชี้วัด RDU ทั้ง 13 ตัว</div>
        </div>
    </a>
</div>

{{-- ============ WATCHLIST + RECENT ACTIVITY ============ --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-2">
    {{-- Watchlist --}}
    <div class="status-card">
        <div class="status-card-head">
            <span class="flex items-center gap-2">
                <span class="msi text-amber-500">crisis_alert</span>
                ตัวชี้วัดที่ต้องเฝ้าระวัง
            </span>
            <span class="text-[.7rem] text-slate-400 font-normal">{{ $watchlist->count() }} รายการ</span>
        </div>
        <div class="status-card-body">
            @forelse($watchlist as $w)
                @php
                    $ind = $w['indicator'];
                    $dotClass = $w['status'] === 'fail' ? 'dot-fail' : 'dot-warning';
                @endphp
                <a href="{{ route('dashboard.kpi', $ind->code) }}" class="status-item hover:bg-slate-50 transition-colors">
                    <div class="status-item-dot {{ $dotClass }}"></div>
                    <div class="flex-1 min-w-0">
                        <div class="status-item-name">
                            <span class="font-mono text-blue-600 mr-1">{{ $ind->code }}</span>
                            {{ \Illuminate\Support\Str::limit($ind->name, 60) }}
                        </div>
                        <div class="status-item-meta">
                            ผลลัพธ์ <b class="{{ $w['status'] === 'fail' ? 'text-red-600' : 'text-amber-600' }}">{{ $w['percentage'] !== null ? $w['percentage'] . '%' : 'N/A' }}</b>
                            · เป้า {{ $ind->targetLabel() }}
                        </div>
                    </div>
                    <span class="msi text-slate-300 text-[.9rem] mt-1">chevron_right</span>
                </a>
            @empty
                <div class="status-empty">
                    <span class="msi">verified</span>
                    <p>ทุกตัวชี้วัดผ่านเกณฑ์ 🎉</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Recent Activity --}}
    <div class="status-card">
        <div class="status-card-head">
            <span class="flex items-center gap-2">
                <span class="msi text-blue-500">history</span>
                ประวัติกิจกรรมล่าสุด
            </span>
            <span class="text-[.7rem] text-slate-400 font-normal">{{ $recentActivity->count() }} รายการ</span>
        </div>
        <div class="status-card-body">
            @forelse($recentActivity as $log)
                <div class="status-item">
                    <div class="status-item-dot dot-info"></div>
                    <div class="flex-1 min-w-0">
                        <div class="status-item-name">{{ $log->action }}</div>
                        <div class="status-item-meta">
                            {{ $log->user?->name ?? 'ระบบ' }} · {{ $log->created_at?->diffForHumans() ?? '—' }}
                        </div>
                    </div>
                </div>
            @empty
                <div class="status-empty">
                    <span class="msi">mail</span>
                    <p>ยังไม่มีประวัติการใช้งาน</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const kpiData = @json($chartData);
    const labels  = kpiData.map(d => d.label);
    const values  = kpiData.map(d => d.pct ?? 0);
    const targets = kpiData.map(d => d.target);
    const colors  = kpiData.map(d => ({
        pass:    'rgba(16,185,129,0.85)',
        warning: 'rgba(245,158,11,0.85)',
        fail:    'rgba(239,68,68,0.85)',
        na:      'rgba(148,163,184,0.4)',
    }[d.status] ?? 'rgba(148,163,184,0.4)'));

    new Chart(document.getElementById('overviewChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'ผลลัพธ์ (%)',
                    data: values,
                    backgroundColor: colors,
                    borderRadius: 6,
                    borderSkipped: false,
                },
                {
                    label: 'เป้าหมาย (%)',
                    data: targets,
                    type: 'line',
                    borderColor: 'rgba(79,70,229,0.9)',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [6, 4],
                    pointRadius: 3,
                    pointBackgroundColor: 'rgba(79,70,229,0.9)',
                    tension: 0,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { font: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif'), size: Math.round(12 * (window.RDU_FONT_SCALE || 1)) } } },
                tooltip: {
                    titleFont: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif') },
                    bodyFont:  { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif') },
                    callbacks: {
                        title: (items) => kpiData[items[0].dataIndex].name,
                        label: (item) => ` ${item.dataset.label}: ${item.parsed.y}%`,
                    },
                },
            },
            scales: {
                x: { ticks: { font: { family: (window.RDU_FONT_MONO || 'Fira Code, monospace'), size: Math.round(11 * (window.RDU_FONT_SCALE || 1)) } }, grid: { color: 'rgba(0,0,0,0.04)' } },
                y: { min: 0, max: 100, ticks: { callback: v => v + '%', font: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif'), size: Math.round(11 * (window.RDU_FONT_SCALE || 1)) } }, grid: { color: 'rgba(0,0,0,0.05)' } },
            },
        },
    });
})();
</script>
@endpush
