@extends('layouts.dashboard')

@section('title', $item['indicator']->code . ': ' . $item['indicator']->name)

@section('content')

@php
    $ind    = $item['indicator'];
    $pct    = $item['percentage'];
    $status = $item['status'];

    $catMeta = [
        'Antibiotic'     => ['label' => 'ยาปฏิชีวนะ',  'icon' => 'medication',        'catBadge' => 'cat-badge-antibiotic', 'trend' => '#dc2626'],
        'DrugQuality'    => ['label' => 'คุณภาพยา',    'icon' => 'prescriptions',      'catBadge' => 'cat-badge-drug',       'trend' => '#059669'],
        'PatientSafety'  => ['label' => 'ความปลอดภัย', 'icon' => 'health_and_safety',  'catBadge' => 'cat-badge-safety',     'trend' => '#4f46e5'],
        'ChronicDisease' => ['label' => 'โรคเรื้อรัง', 'icon' => 'bloodtype',          'catBadge' => 'cat-badge-chronic',    'trend' => '#0891b2'],
    ];
    $meta = $catMeta[$ind->category] ?? ['label' => 'อื่นๆ', 'icon' => 'category', 'catBadge' => '', 'trend' => '#3b82f6'];

    $pctColor = match($status) {
        'pass'    => 'text-emerald-600',
        'warning' => 'text-amber-600',
        'fail'    => 'text-red-600',
        default   => 'text-slate-400',
    };
    $statusMeta = match($status) {
        'pass'    => ['label' => 'ผ่านเกณฑ์',    'chip' => 'status-pass',    'icon' => 'check_circle'],
        'warning' => ['label' => 'ใกล้เป้าหมาย', 'chip' => 'status-warning', 'icon' => 'warning'],
        'fail'    => ['label' => 'ไม่ผ่านเกณฑ์', 'chip' => 'status-fail',    'icon' => 'cancel'],
        default   => ['label' => 'ไม่มีข้อมูล',   'chip' => 'status-na',      'icon' => 'remove'],
    };
    $barColor = match($status) {
        'pass'    => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'fail'    => 'bg-red-500',
        default   => 'bg-slate-300',
    };
@endphp

{{-- ============ HEADER ============ --}}
<div class="mb-5">
    <a href="{{ route('dashboard') }}"
       class="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-800 mb-3 cursor-pointer transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 rounded">
        <span class="msi text-[1rem]">arrow_back</span>
        กลับภาพรวม
    </a>

    <div class="flex flex-wrap items-start gap-3">
        <span class="cat-badge {{ $meta['catBadge'] }} text-base px-3 py-1">{{ $ind->code }}</span>
        <div class="flex-1 min-w-0">
            <h1 class="text-xl font-bold text-slate-800 leading-tight">{{ $ind->name }}</h1>
            <div class="flex items-center gap-2 mt-1.5 text-xs text-slate-500">
                <span class="msi text-[.9rem]">{{ $meta['icon'] }}</span>
                หมวด{{ $meta['label'] }}
                <span class="text-slate-300">·</span>
                <span>เป้าหมาย <b class="text-slate-700">{{ $ind->targetLabel() }}</b></span>
            </div>
            @if($ind->description)
                <p class="text-slate-500 text-sm mt-2">{{ $ind->description }}</p>
            @endif
        </div>
    </div>
</div>

{{-- ============ HERO METRIC + TREND ============ --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">

    {{-- Metric Card --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <div class="text-xs text-slate-500 mb-1 uppercase tracking-wider font-semibold">ผลลัพธ์</div>
        <div class="flex items-baseline gap-1 mb-3">
            <span class="text-5xl font-bold tabular-nums {{ $pctColor }}">
                {{ $pct !== null ? $pct : 'N/A' }}
            </span>
            @if($pct !== null)
                <span class="text-2xl font-semibold {{ $pctColor }} opacity-80">%</span>
            @endif
        </div>

        <span class="status-badge {{ $statusMeta['chip'] }} text-sm mb-4">
            <span class="msi text-[1rem]">{{ $statusMeta['icon'] }}</span>
            {{ $statusMeta['label'] }}
        </span>

        {{-- Progress bar --}}
        @if($pct !== null)
            <div class="w-full bg-slate-100 rounded-full h-2 mt-4 mb-1 overflow-hidden">
                <div class="h-2 rounded-full {{ $barColor }} animate-progress"
                     style="width: {{ min($pct, 100) }}%"
                     role="progressbar"
                     aria-valuenow="{{ $pct }}"
                     aria-valuemin="0"
                     aria-valuemax="100"></div>
            </div>
            <div class="text-[.7rem] text-slate-400 tabular-nums">0% ── เป้า {{ $ind->targetLabel() }} ── 100%</div>
        @endif

        <table class="w-full text-sm mt-5 border-t border-slate-100 pt-3">
            <tbody>
                <tr>
                    <td class="text-slate-500 py-1.5 text-xs">ตัวเศษ</td>
                    <td class="text-right font-semibold tabular-nums">{{ number_format($item['numerator']) }}</td>
                </tr>
                <tr>
                    <td class="text-slate-500 py-1.5 text-xs">ตัวส่วน</td>
                    <td class="text-right font-semibold tabular-nums">{{ number_format($item['denominator']) }}</td>
                </tr>
                <tr class="border-t border-slate-100">
                    <td class="text-slate-500 py-1.5 pt-2 text-xs">เป้าหมาย</td>
                    <td class="text-right font-semibold">{{ $ind->targetLabel() }}</td>
                </tr>
                <tr>
                    <td class="text-slate-500 py-1.5 text-xs">ทิศทาง</td>
                    <td class="text-right text-xs">
                        {{ $ind->target_direction === 'higher' ? 'ยิ่งสูงยิ่งดี' : 'ยิ่งต่ำยิ่งดี' }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Trend Chart --}}
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-200 flex items-center gap-2 font-semibold text-sm text-slate-800">
            <span class="msi text-blue-600">trending_up</span>
            แนวโน้มรายเดือน (ปีงบประมาณ {{ $year }})
        </div>
        <div class="p-4">
            <div style="height: 260px;">
                <canvas id="trendChart"
                        aria-label="กราฟแนวโน้ม {{ $ind->code }} รายเดือน"
                        role="img"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ============ DESCRIPTION CARD (numerator/denominator) ============ --}}
@if($ind->numerator_desc || $ind->denominator_desc)
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-5">
        <div class="flex items-center gap-2 mb-3 font-semibold text-sm text-slate-800">
            <span class="msi text-indigo-600">description</span>
            คำนิยามตัวชี้วัด
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            @if($ind->numerator_desc)
                <div class="p-3 bg-blue-50 rounded-lg text-blue-800">
                    <div class="font-semibold text-xs uppercase tracking-wider mb-1">ตัวเศษ (Numerator)</div>
                    <p class="text-sm">{{ $ind->numerator_desc }}</p>
                </div>
            @endif
            @if($ind->denominator_desc)
                <div class="p-3 bg-emerald-50 rounded-lg text-emerald-800">
                    <div class="font-semibold text-xs uppercase tracking-wider mb-1">ตัวส่วน (Denominator)</div>
                    <p class="text-sm">{{ $ind->denominator_desc }}</p>
                </div>
            @endif
        </div>
    </div>
@endif

{{-- ============ EXPORT SHORTCUTS ============ --}}
@php
    $exportParams = ['code' => $ind->code, 'year' => $year, 'month' => $month];
@endphp
<div class="section-head">
    <span class="msi">download</span>
    ดาวน์โหลดรายงาน
</div>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
    <a href="{{ route('kpi.export.excel', $exportParams) }}" class="shortcut-card">
        <div class="sc-icon sc-green"><span class="msi">table_view</span></div>
        <div>
            <div class="text-sm font-bold text-slate-800">Export Excel</div>
            <div class="text-xs text-slate-500 mt-0.5">รูปแบบไฟล์ .xlsx เปิดใน Excel</div>
        </div>
    </a>
    <a href="{{ route('kpi.export.csv', $exportParams) }}" class="shortcut-card">
        <div class="sc-icon"><span class="msi">description</span></div>
        <div>
            <div class="text-sm font-bold text-slate-800">Export CSV</div>
            <div class="text-xs text-slate-500 mt-0.5">รูปแบบ Comma-Separated Values</div>
        </div>
    </a>
    <a href="{{ route('kpi.export.pdf', $exportParams) }}" class="shortcut-card">
        <div class="sc-icon sc-red"><span class="msi">picture_as_pdf</span></div>
        <div>
            <div class="text-sm font-bold text-slate-800">Export PDF</div>
            <div class="text-xs text-slate-500 mt-0.5">รายงานพร้อมส่ง — พิมพ์ได้ทันที</div>
        </div>
    </a>
</div>

{{-- ============ DETAIL TABLE ============ --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between gap-2">
        <div class="flex items-center gap-2 font-semibold text-sm text-slate-800">
            <span class="msi text-blue-600">table_rows</span>
            รายการข้อมูลรายกรณี
        </div>
        @if($details->isNotEmpty())
            <span class="text-xs text-slate-500">
                แสดง <b class="text-slate-700 tabular-nums">{{ $details->count() }}</b> รายการ
                @if($details->count() >= 500)
                    <span class="ml-1 text-amber-600 font-medium">(จำกัดสูงสุด 500)</span>
                @endif
            </span>
        @endif
    </div>

    @if($details->isEmpty())
        <div class="status-empty">
            <span class="msi">inbox</span>
            <p class="font-medium">ยังไม่มีข้อมูลรายกรณี</p>
            <p class="text-xs text-slate-400">ข้อมูลจะแสดงเมื่อ HOSxP Query ของตัวชี้วัดนี้พร้อมใช้งาน</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs">
                    <tr>
                        @foreach($details->first()->keys() as $col)
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap border-b border-slate-200">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($details as $row)
                        <tr class="hover:bg-blue-50/40 transition-colors">
                            @foreach($row as $val)
                                <td class="px-3 py-2 text-slate-700">{{ $val }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const trend      = @json($trendData);
    const target     = {{ $ind->target_value }};
    const catColor   = @json($meta['trend']);

    const labels = trend.map(d => d.label);
    const values = trend.map(d => d.pct);
    const pointColors = trend.map(d => ({
        pass:    'rgba(16,185,129,0.95)',
        warning: 'rgba(245,158,11,0.95)',
        fail:    'rgba(239,68,68,0.95)',
        na:      'rgba(148,163,184,0.6)',
    }[d.status] ?? 'rgba(59,130,246,0.8)'));

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'ผลลัพธ์ (%)',
                    data: values,
                    borderColor: catColor,
                    backgroundColor: catColor + '18',
                    pointBackgroundColor: pointColors,
                    pointBorderColor: pointColors,
                    pointBorderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    spanGaps: true,
                    borderWidth: 2.5,
                },
                {
                    label: 'เป้าหมาย (%)',
                    data: labels.map(() => target),
                    borderColor: 'rgba(79,70,229,0.7)',
                    backgroundColor: 'transparent',
                    borderDash: [6, 4],
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    borderWidth: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { font: { family: 'Sarabun, sans-serif', size: 12 } } },
                tooltip: {
                    titleFont: { family: 'Sarabun, sans-serif' },
                    bodyFont:  { family: 'Sarabun, sans-serif' },
                    callbacks: {
                        label: (item) => {
                            if (item.datasetIndex === 1) return ` เป้าหมาย: ${item.parsed.y}%`;
                            const d = trend[item.dataIndex];
                            if (d.pct === null) return ' ไม่มีข้อมูล';
                            return [` ผลลัพธ์: ${d.pct}%`, ` ${d.num ?? '-'} / ${d.den ?? '-'}`];
                        },
                    },
                },
            },
            scales: {
                x: { ticks: { font: { family: 'Sarabun, sans-serif', size: 11 } }, grid: { color: 'rgba(0,0,0,0.04)' } },
                y: { min: 0, max: 100, ticks: { callback: v => v + '%', font: { family: 'Sarabun, sans-serif', size: 11 } }, grid: { color: 'rgba(0,0,0,0.05)' } },
            },
        },
    });
})();
</script>
@endpush
