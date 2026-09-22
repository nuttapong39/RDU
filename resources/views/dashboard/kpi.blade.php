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

    // Months (1-12) belonging to the currently active filter, for highlighting on the trend chart.
    $activeMonths = $quarter
        ? \App\Support\FiscalDateRange::quarterMonths($quarter)
        : ($month > 0 ? [$month] : []);
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

{{-- ============ QUARTERLY HERO CARDS ============ --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    @foreach($quarterlyBreakdown as $qb)
        @php
            $qPct    = $qb['percentage'];
            $qStatus = $qb['status'];
            $qColor  = match ($qStatus) {
                'pass'    => 'text-emerald-600',
                'warning' => 'text-amber-600',
                'fail'    => 'text-red-600',
                default   => 'text-slate-400',
            };
            $qDot = match ($qStatus) {
                'pass'    => 'bg-emerald-500',
                'warning' => 'bg-amber-500',
                'fail'    => 'bg-red-500',
                default   => 'bg-slate-300',
            };
            $qIsActive = $quarter === $qb['quarter'];
        @endphp
        <a href="{{ route('dashboard.kpi', ['code' => $ind->code, 'year' => $year, 'quarter' => $qb['quarter']]) }}"
           class="group bg-white rounded-xl border p-4 shadow-sm cursor-pointer relative overflow-hidden
                  hover:shadow-xl hover:shadow-blue-100 hover:-translate-y-1 hover:scale-[1.02]
                  hover:border-blue-400 hover:ring-2 hover:ring-blue-100 hover:bg-blue-50
                  transition-all duration-200 ease-out
                  {{ $qIsActive ? 'border-blue-400 ring-2 ring-blue-100' : 'border-slate-200' }}">

            {{-- Decorative watermark icon — same monochrome/low-opacity pattern as the main KPI cards, brightens on hover --}}
            <span class="msi absolute pointer-events-none select-none text-slate-500 opacity-[0.02] transition-opacity duration-200 group-hover:opacity-[0.08]"
                  aria-hidden="true"
                  style="right: -14px; top: -10px; font-size: 88px; line-height: 1; z-index: 0;">
                {{ $meta['icon'] }}
            </span>

            <div class="relative flex items-center gap-1.5 text-xs text-slate-500 font-semibold mb-1.5">
                <span class="w-1.5 h-1.5 rounded-full {{ $qDot }}"></span>
                ไตรมาส {{ $qb['quarter'] }}
            </div>
            <div class="relative flex items-baseline gap-0.5">
                <span class="text-2xl font-bold tabular-nums {{ $qColor }}">{{ $qPct !== null ? $qPct : 'N/A' }}</span>
                @if($qPct !== null)
                    <span class="text-sm font-semibold {{ $qColor }} opacity-80">%</span>
                @endif
            </div>
            <div class="relative text-[.7rem] text-slate-400 mt-1 tabular-nums">
                {{ number_format($qb['numerator']) }} / {{ number_format($qb['denominator']) }} ราย
            </div>
        </a>
    @endforeach
</div>

{{-- ============ FILTER TOOLBAR ============ --}}
<form method="GET" action="{{ route('dashboard.kpi', $ind->code) }}"
      class="bg-white rounded-xl border border-slate-200 px-4 py-3 mb-5 shadow-sm">
    <input type="hidden" name="quarter" id="quarterInput" value="{{ $quarter ?? '' }}">

    <div class="flex flex-wrap items-center gap-3">
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

        {{-- Segmented period buttons: ทั้งปี + ไตรมาส 1-4 --}}
        <div class="inline-flex rounded-lg border border-slate-300 overflow-hidden shadow-sm" role="group" aria-label="เลือกช่วงเวลา">
            <button type="button"
                    onclick="document.getElementById('quarterInput').value=''; this.form.submit();"
                    class="px-3 py-1.5 text-sm font-medium transition-colors cursor-pointer whitespace-nowrap
                           {{ !$quarter && $month === 0 ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                ทั้งปี
            </button>
            @foreach([1, 2, 3, 4] as $q)
                <button type="button"
                        onclick="document.getElementById('quarterInput').value='{{ $q }}'; this.form.submit();"
                        class="px-3 py-1.5 text-sm font-medium border-l border-slate-300 transition-colors cursor-pointer whitespace-nowrap
                               {{ $quarter === $q ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                    ไตรมาส {{ $q }}
                </button>
            @endforeach
        </div>

        <div class="flex items-center gap-2">
            <label for="filter-month" class="text-xs text-slate-500 font-medium whitespace-nowrap">หรือเลือกเดือน</label>
            <select id="filter-month" name="month"
                    onchange="document.getElementById('quarterInput').value=''"
                    class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none cursor-pointer bg-white">
                <option value="0" @selected(!$quarter && $month === 0)>ทั้งปี</option>
                @foreach($monthOptions as $num => $name)
                    <option value="{{ $num }}" @selected(!$quarter && $num === $month)>{{ $name }}</option>
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
    </div>

    @if($quarter)
        <div class="mt-2.5 inline-flex items-center gap-1.5 text-xs text-blue-700 bg-blue-50 px-2.5 py-1 rounded-lg">
            <span class="msi text-[.9rem]">date_range</span>
            กำลังดู {{ \App\Support\FiscalDateRange::quarterLabel($quarter) }} · ปีงบ {{ $year }}
        </div>
    @endif
</form>

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
                @if($ind->code === 'KPI01')
                    <tr>
                        <td class="text-slate-500 py-1.5 text-xs">บาดแผลจากอุบัติเหตุ (ทั้งปีงบ {{ $year }})</td>
                        <td class="text-right font-semibold tabular-nums">{{ number_format($quarterlyBreakdown->sum('denominator')) }} เคส</td>
                    </tr>
                    @if($quarter)
                        <tr>
                            <td class="text-slate-500 py-1.5 text-xs">ไตรมาส {{ $quarter }}</td>
                            <td class="text-right font-semibold tabular-nums">{{ number_format($detailsGrandTotal) }} เคส</td>
                        </tr>
                    @endif
                @endif
                <tr class="border-t border-slate-100">
                    <td class="text-slate-500 py-1.5 pt-2 text-xs">ตัวเศษ</td>
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
        <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 font-semibold text-sm text-slate-800">
                <span class="msi text-blue-600">trending_up</span>
                แนวโน้มรายเดือน (ปีงบประมาณ {{ $year }})
            </div>
            @if(!empty($activeMonths))
                <span class="inline-flex items-center gap-1.5 text-xs text-blue-700">
                    <span class="inline-block w-2.5 h-2.5 rounded-full border-2 border-[#2563eb] bg-white"></span>
                    ช่วงที่กำลังดูอยู่
                </span>
            @endif
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

{{-- ============ DEEP-DIVE ANALYSIS (KPI01, KPI03, KPI04, KPI05 — any KPI with real detail data) ============ --}}
@if($hasDeepDive)
<div class="section-head">
    <span class="msi">insights</span>
    การวิเคราะห์เชิงลึก
</div>

{{-- Doctor antibiotic rate — full width, vertical bars (scrolls horizontally past ~10 doctors so
     labels stay readable rather than cramming or truncating them) --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-5">
    <div class="flex items-center justify-between gap-3 mb-1 flex-wrap">
        <div class="flex items-center gap-2 font-semibold text-sm text-slate-800">
            <span class="msi text-purple-600">medical_services</span>
            จำนวนเคสที่ได้รับยาปฏิชีวนะรายแพทย์ผู้ตรวจ
        </div>
        @if(count($doctorRate) > 0)
            {{-- Status legend — bars are sized by case count, colored by the underlying rate vs
                 the {{ $ind->target_value }}% target (still in the tooltip), not the only signal --}}
            <div class="flex items-center gap-3 text-[.7rem] text-slate-500 flex-wrap">
                <span class="flex items-center gap-1"><span class="inline-block w-2.5 h-2.5 rounded-full" style="background:#10b981"></span>ผ่านเกณฑ์</span>
                <span class="flex items-center gap-1"><span class="inline-block w-2.5 h-2.5 rounded-full" style="background:#f59e0b"></span>ใกล้เป้าหมาย</span>
                <span class="flex items-center gap-1"><span class="inline-block w-2.5 h-2.5 rounded-full" style="background:#ef4444"></span>ไม่ผ่านเกณฑ์</span>
                <span class="flex items-center gap-1"><span class="inline-block w-2.5 h-2.5 rounded-full" style="background:#94a3b8"></span>ไม่ระบุแพทย์</span>
            </div>
        @endif
    </div>
    @if(count($doctorRate) > 0)
        <div class="flex items-start gap-2.5 mb-4 p-3 rounded-lg bg-indigo-50 border border-indigo-100">
            <span class="msi text-indigo-500 text-[1.1rem] flex-shrink-0 mt-0.5">info</span>
            <p class="text-sm text-indigo-900 leading-relaxed">
                <strong>ความสูงแท่ง = จำนวนเคส</strong>ที่แพทย์คนนั้นสั่งยาปฏิชีวนะ
                <span class="text-indigo-700">(ไม่ใช่ % แล้ว เพื่อไม่ให้เคสน้อยๆ เช่น 1 เคส ดูเหมือนอัตรา 100%)</span>
                &nbsp;·&nbsp; <strong>สีของแท่ง</strong> ยังสะท้อนอัตรา % เทียบเกณฑ์เป้าหมาย {{ $ind->target_value }}% เดิม
                &nbsp;·&nbsp; ชี้ที่แท่งเพื่อดู <strong>อัตรา %</strong> และ<strong>จำนวนเคสทั้งหมด (n)</strong> ของแพทย์คนนั้น
            </p>
        </div>
        <div class="overflow-x-auto">
            <div style="height: 380px; min-width: {{ max(640, count($doctorRate) * 78) }}px;">
                <canvas id="doctorRateChart" aria-label="จำนวนเคสที่ได้รับยาปฏิชีวนะรายแพทย์ผู้ตรวจ" role="img"></canvas>
            </div>
        </div>
    @else
        <div class="status-empty py-8">
            <span class="msi">medical_services</span>
            <p class="font-medium text-sm">ยังไม่มีข้อมูลแพทย์ในช่วงเวลานี้</p>
        </div>
    @endif
</div>

{{-- ICD-10 ranking + drug usage, paired side by side — deliberately distinct header/icon/palette
     from the quarterly donut further down so the two never get confused --}}
@php
    $icd10Colors = ['#e11d48','#f97316','#eab308','#84cc16','#10b981','#06b6d4','#3b82f6','#8b5cf6','#d946ef','#f43f5e','#94a3b8'];
@endphp
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <div class="flex items-center gap-2 mb-3 font-semibold text-sm text-slate-800">
            <span class="msi text-rose-600">local_hospital</span>
            การวินิจฉัย ICD-10 ที่พบบ่อย 10 อันดับแรก
        </div>
        @if(count($icd10Ranking['items']) > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="relative mx-auto" style="height: 220px; max-width: 220px;">
                    <canvas id="icd10DonutChart" aria-label="สัดส่วนรหัสวินิจฉัย ICD-10" role="img"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-2xl font-bold text-slate-800 tabular-nums">{{ number_format($icd10Ranking['total']) }}</span>
                        <span class="text-[.65rem] text-slate-400">Dx ทั้งหมด</span>
                    </div>
                </div>
                <div class="flex flex-col gap-2 text-sm">
                    @foreach($icd10Ranking['items'] as $i => $row)
                        <div class="flex items-start justify-between gap-2">
                            <span class="flex items-start gap-2 min-w-0">
                                <span class="inline-block w-2.5 h-2.5 rounded-full mt-1 flex-shrink-0" style="background: {{ $icd10Colors[$i] ?? '#94a3b8' }}"></span>
                                <span class="min-w-0">
                                    @if($row['code'])
                                        <span class="font-semibold text-slate-700">{{ $row['code'] }}</span>
                                    @endif
                                    @if($row['tname'] || $row['name'])
                                        <span class="text-slate-500 text-xs block truncate" title="{{ $row['name'] }}">
                                            {{ $row['tname'] ?: $row['name'] }}
                                        </span>
                                    @endif
                                </span>
                            </span>
                            <span class="flex items-center gap-2 tabular-nums flex-shrink-0">
                                <span class="text-slate-400 text-xs">{{ number_format($row['count']) }}</span>
                                <span class="font-semibold text-slate-700 w-12 text-right text-xs">{{ $row['percent'] }}%</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="status-empty py-8">
                <span class="msi">local_hospital</span>
                <p class="font-medium text-sm">ยังไม่มีข้อมูลการวินิจฉัยในช่วงเวลานี้</p>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <div class="flex items-center gap-2 mb-3 font-semibold text-sm text-slate-800">
            <span class="msi text-blue-600">bar_chart</span>
            สัดส่วนยาปฏิชีวนะที่สั่งจ่าย
        </div>
        @if(count($drugUsage) > 0)
            <div style="height: {{ max(220, count($drugUsage) * 32) }}px;">
                <canvas id="drugUsageChart" aria-label="สัดส่วนยาปฏิชีวนะที่สั่งจ่าย" role="img"></canvas>
            </div>
        @else
            <div class="status-empty py-8">
                <span class="msi">bar_chart</span>
                <p class="font-medium text-sm">ยังไม่มีข้อมูลยาปฏิชีวนะในช่วงเวลานี้</p>
            </div>
        @endif
    </div>
</div>

<div class="section-head">
    <span class="msi">calendar_view_month</span>
    สรุปรายไตรมาส
</div>
@endif

{{-- ============ QUARTERLY PIE CHART + DESCRIPTION CARD ============ --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
    {{-- Pie chart: share of antibiotic-positive cases (numerator) across the 4 quarters --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <div class="flex items-center gap-2 mb-3 font-semibold text-sm text-slate-800">
            <span class="msi text-indigo-600">calendar_view_month</span>
            สัดส่วนเคส ATB แยกรายไตรมาส (Q1–Q4 ปีงบ {{ $year }})
        </div>
        @if($quarterlyBreakdown->sum('numerator') > 0)
            <div class="relative mx-auto" style="height: 200px; max-width: 200px;">
                <canvas id="quarterPieChart" aria-label="สัดส่วนเคสที่ได้รับยาปฏิชีวนะรายไตรมาส" role="img"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-2xl font-bold text-slate-800 tabular-nums">{{ number_format($quarterlyBreakdown->sum('numerator')) }}</span>
                    <span class="text-[.65rem] text-slate-400">เคสทั้งปี</span>
                </div>
            </div>
            {{-- Custom legend (replaces Chart.js default legend) --}}
            <div id="quarterPieLegend" class="flex flex-col gap-1.5 mt-4"></div>
        @else
            <div class="status-empty py-8">
                <span class="msi">calendar_view_month</span>
                <p class="font-medium text-sm">ยังไม่มีข้อมูลเพียงพอสำหรับปีงบนี้</p>
            </div>
        @endif
    </div>

    {{-- Description card, compacted to fit the narrower column --}}
    @if($ind->numerator_desc || $ind->denominator_desc)
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-2 mb-3 font-semibold text-sm text-slate-800">
                <span class="msi text-indigo-600">description</span>
                คำนิยามตัวชี้วัด
            </div>
            <div class="space-y-3 text-sm">
                @if($ind->numerator_desc)
                    <div class="p-3 bg-blue-50 rounded-lg text-blue-800">
                        <div class="font-semibold text-xs uppercase tracking-wider mb-1">ตัวเศษ (Numerator)</div>
                        <p class="text-sm line-clamp-2">{{ $ind->numerator_desc }}</p>
                    </div>
                @endif
                @if($ind->denominator_desc)
                    <div class="p-3 bg-emerald-50 rounded-lg text-emerald-800">
                        <div class="font-semibold text-xs uppercase tracking-wider mb-1">ตัวส่วน (Denominator)</div>
                        <p class="text-sm line-clamp-2">{{ $ind->denominator_desc }}</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

{{-- ============ EXPORT ============ --}}
@php
    // Pre-fill the export modal's date range with whatever period is currently on screen,
    // so most of the time the user just confirms the format without retyping dates.
    if ($quarter) {
        [$prefillFrom, $prefillTo] = \App\Support\FiscalDateRange::quarterToGregorian($year, $quarter);
    } else {
        [$prefillFrom, $prefillTo] = \App\Support\FiscalDateRange::toGregorian($year, $month);
    }
@endphp
<div class="section-head">
    <span class="msi">download</span>
    ดาวน์โหลดรายงาน
</div>

<button type="button" onclick="document.getElementById('dateRangeModal').classList.remove('hidden')"
        class="w-full flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 bg-white shadow-sm hover:bg-slate-50 transition-colors cursor-pointer text-left mb-6
               focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
    <div class="sc-icon"><span class="msi">download</span></div>
    <div>
        <div class="text-sm font-bold text-slate-800">Export รายงาน...</div>
        <div class="text-xs text-slate-500 mt-0.5">เลือกช่วงวันที่และฟอร์แมต (Excel / CSV / PDF) — ครบทุกรายการเสมอ</div>
    </div>
</button>

{{-- ============ EXPORT MODAL ============ --}}
<div id="dateRangeModal" class="{{ $errors->has('date_from') || $errors->has('date_to') ? '' : 'hidden' }} fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-slate-800 flex items-center gap-2">
                <span class="msi text-blue-600">download</span>
                Export รายงาน
            </h3>
            <button type="button" onclick="document.getElementById('dateRangeModal').classList.add('hidden')"
                    class="text-slate-400 hover:text-slate-600 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 rounded">
                <span class="msi">close</span>
            </button>
        </div>

        <form method="GET">
            <div class="grid grid-cols-2 gap-3 mb-2">
                <div>
                    <label for="dateFromInput" class="text-xs text-slate-500 font-medium block mb-1">วันที่เริ่มต้น</label>
                    <input type="date" name="date_from" id="dateFromInput" required value="{{ old('date_from', $prefillFrom) }}"
                           class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>
                <div>
                    <label for="dateToInput" class="text-xs text-slate-500 font-medium block mb-1">วันที่สิ้นสุด</label>
                    <input type="date" name="date_to" id="dateToInput" required value="{{ old('date_to', $prefillTo) }}"
                           class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>
            </div>
            <p class="text-xs text-slate-400 mb-4">ค่าเริ่มต้นคือช่วงที่กำลังดูอยู่ — แก้ไขได้ (สูงสุด 1 ปีงบประมาณ) แล้วเลือกฟอร์แมตด้านล่างเพื่อดาวน์โหลด</p>

            <div class="grid grid-cols-3 gap-2">
                <button type="submit" formaction="{{ route('kpi.export.excel', $ind->code) }}"
                        class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium cursor-pointer transition-colors">
                    Excel
                </button>
                <button type="submit" formaction="{{ route('kpi.export.csv', $ind->code) }}"
                        class="px-3 py-2 bg-slate-600 hover:bg-slate-700 text-white rounded-lg text-sm font-medium cursor-pointer transition-colors">
                    CSV
                </button>
                <button type="submit" formaction="{{ route('kpi.export.pdf', $ind->code) }}"
                        class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium cursor-pointer transition-colors">
                    PDF
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ============ DETAIL TABLE ============ --}}
@php
    $baseParams = ['code' => $ind->code, 'year' => $year];
    if ($quarter) { $baseParams['quarter'] = $quarter; } else { $baseParams['month'] = $month; }
    if ($sortCol) { $baseParams['sort'] = $sortCol; $baseParams['dir'] = $sortDir; }

    $pageParams = $baseParams;
    if ($search !== '') { $pageParams['q'] = $search; }
    if ($colDateFrom) { $pageParams['col_date_from'] = $colDateFrom; }
    if ($colDateTo) { $pageParams['col_date_to'] = $colDateTo; }

    $hasActiveFilter = $search !== '' || $colDateFrom || $colDateTo;
    $longTextCols = ['CC', 'HPI'];
@endphp
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between gap-2 flex-wrap">
        <div class="flex items-center gap-2 font-semibold text-sm text-slate-800">
            <span class="msi text-blue-600">table_rows</span>
            รายการข้อมูลรายกรณี
        </div>
        @if($detailsTotal > 0 || $hasActiveFilter)
            <span class="text-xs text-slate-500">
                @if($hasActiveFilter)
                    พบ <b class="text-slate-700 tabular-nums">{{ number_format($detailsTotal) }}</b> จากทั้งหมด {{ number_format($detailsGrandTotal) }} รายการ
                @else
                    ทั้งหมด <b class="text-slate-700 tabular-nums">{{ number_format($detailsTotal) }}</b> รายการ
                @endif
                @if($detailsLastPage > 1)
                    · หน้า <b class="text-slate-700 tabular-nums">{{ $detailsPage }}</b> จาก {{ $detailsLastPage }}
                @endif
            </span>
        @endif
    </div>

    {{-- Search + date-column filter --}}
    <div class="px-5 py-3 border-b border-slate-200 bg-slate-50/50">
        <form method="GET" action="{{ route('dashboard.kpi', $ind->code) }}" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="year" value="{{ $year }}">
            @if($quarter)
                <input type="hidden" name="quarter" value="{{ $quarter }}">
            @else
                <input type="hidden" name="month" value="{{ $month }}">
            @endif
            @if($sortCol)
                <input type="hidden" name="sort" value="{{ $sortCol }}">
                <input type="hidden" name="dir" value="{{ $sortDir }}">
            @endif

            <div class="relative flex-1 min-w-[200px] max-w-xs">
                <span class="msi text-[1.1rem] text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none">search</span>
                <input type="text" name="q" value="{{ $search }}" placeholder="ค้นหา HN, VN, ชื่อ..."
                       class="w-full border border-slate-300 rounded-lg pl-8 pr-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div class="flex items-center gap-1.5 text-xs text-slate-500 whitespace-nowrap">
                <span>วันที่บริการ</span>
                <input type="date" name="col_date_from" value="{{ $colDateFrom }}"
                       class="border border-slate-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                <span>ถึง</span>
                <input type="date" name="col_date_to" value="{{ $colDateTo }}"
                       class="border border-slate-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <button type="submit"
                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium cursor-pointer transition-colors">
                <span class="msi text-[1rem]">search</span> ค้นหา
            </button>

            @if($hasActiveFilter)
                <a href="{{ route('dashboard.kpi', $baseParams) }}"
                   class="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-red-600 cursor-pointer transition-colors">
                    <span class="msi text-[1rem]">close</span> ล้างตัวกรอง
                </a>
            @endif
        </form>
    </div>

    @if($details->isEmpty())
        <div class="status-empty">
            <span class="msi">{{ $hasActiveFilter ? 'search_off' : 'inbox' }}</span>
            @if($hasActiveFilter)
                <p class="font-medium">ไม่พบรายการที่ตรงกับตัวกรอง</p>
                <p class="text-xs text-slate-400">ลองแก้คำค้นหาหรือช่วงวันที่ดู</p>
            @else
                <p class="font-medium">ยังไม่มีข้อมูลรายกรณี</p>
                <p class="text-xs text-slate-400">ข้อมูลจะแสดงเมื่อ HOSxP Query ของตัวชี้วัดนี้พร้อมใช้งาน</p>
            @endif
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs">
                    <tr>
                        @foreach(array_keys($details->first()) as $col)
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap border-b border-slate-200">
                                <div class="inline-flex items-center gap-0.5">
                                    <span>{{ $col }}</span>
                                    <span class="inline-flex flex-col justify-center">
                                        <a href="{{ route('dashboard.kpi', array_merge($pageParams, ['sort' => $col, 'dir' => 'asc', 'page' => 1])) }}"
                                           title="เรียง {{ $col }} จากน้อยไปมาก" aria-label="เรียง {{ $col }} จากน้อยไปมาก"
                                           class="msi text-[1rem] leading-[.6] cursor-pointer transition-colors
                                                  {{ $sortCol === $col && $sortDir === 'asc' ? 'text-blue-600' : 'text-slate-300 hover:text-slate-500' }}">
                                            arrow_drop_up
                                        </a>
                                        <a href="{{ route('dashboard.kpi', array_merge($pageParams, ['sort' => $col, 'dir' => 'desc', 'page' => 1])) }}"
                                           title="เรียง {{ $col }} จากมากไปน้อย" aria-label="เรียง {{ $col }} จากมากไปน้อย"
                                           class="msi text-[1rem] leading-[.6] -mt-1 cursor-pointer transition-colors
                                                  {{ $sortCol === $col && $sortDir === 'desc' ? 'text-blue-600' : 'text-slate-300 hover:text-slate-500' }}">
                                            arrow_drop_down
                                        </a>
                                    </span>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($details as $row)
                        <tr class="hover:bg-blue-100 transition-colors">
                            @foreach($row as $col => $val)
                                @if(in_array($col, $longTextCols, true) && mb_strlen((string) $val) > 50)
                                    <td class="px-3 py-2 text-slate-700 max-w-[220px]">
                                        <div class="group/tip relative">
                                            <div class="truncate cursor-help">{{ Str::limit((string) $val, 50) }}</div>
                                            <div class="hidden group-hover/tip:block absolute z-20 left-0 top-full mt-1 w-80 max-w-[85vw] p-3
                                                        bg-slate-800 text-white text-xs rounded-lg shadow-lg whitespace-pre-wrap leading-relaxed">
                                                {{ $val }}
                                            </div>
                                        </div>
                                    </td>
                                @else
                                    <td class="px-3 py-2 text-slate-700">{{ $val }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($detailsLastPage > 1)
            <div class="px-5 py-3 border-t border-slate-200 flex items-center justify-between gap-2">
                @if($detailsPage > 1)
                    <a href="{{ route('dashboard.kpi', array_merge($pageParams, ['page' => $detailsPage - 1])) }}"
                       class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800 cursor-pointer">
                        <span class="msi text-[1rem]">chevron_left</span> ก่อนหน้า
                    </a>
                @else
                    <span class="inline-flex items-center gap-1 text-sm text-slate-300">
                        <span class="msi text-[1rem]">chevron_left</span> ก่อนหน้า
                    </span>
                @endif

                <span class="text-xs text-slate-500 tabular-nums">หน้า {{ $detailsPage }} จาก {{ $detailsLastPage }}</span>

                @if($detailsPage < $detailsLastPage)
                    <a href="{{ route('dashboard.kpi', array_merge($pageParams, ['page' => $detailsPage + 1])) }}"
                       class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800 cursor-pointer">
                        ถัดไป <span class="msi text-[1rem]">chevron_right</span>
                    </a>
                @else
                    <span class="inline-flex items-center gap-1 text-sm text-slate-300">
                        ถัดไป <span class="msi text-[1rem]">chevron_right</span>
                    </span>
                @endif
            </div>
        @endif
    @endif
</div>

@endsection

@php
    $exceedsTarget = $status === 'fail' || $status === 'warning';
@endphp
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    // Warn when the currently viewed period is off-target (fail or warning) —
    // once per browser session per (KPI code + period) so it doesn't nag on every reload.
    const exceedsTarget = @json($exceedsTarget);
    if (exceedsTarget) {
        const periodKey = @json($ind->code.':'.$year.':'.($quarter ? 'q'.$quarter : $month));
        const seenKey    = 'rdu_alert_' + periodKey;
        if (!sessionStorage.getItem(seenKey)) {
            sessionStorage.setItem(seenKey, '1');
            const directionWord = @json($ind->target_direction === 'lower' ? 'เกิน' : 'ต่ำกว่า');
            Swal.fire({
                icon: 'warning',
                title: 'ไม่ผ่านเกณฑ์ที่ตั้งไว้',
                html: `<b>{{ $ind->code }}</b> อยู่ที่ <b style="color:#dc2626">${ {{ $pct ?? 'null' }} }%</b> `
                    + `ซึ่ง${directionWord}เป้าหมาย <b>{{ $ind->targetLabel() }}</b>`,
                confirmButtonText: 'รับทราบ',
                confirmButtonColor: '#dc2626',
            });
        }
    }
})();
</script>
@if($ind->code === 'KPI03')
<script>
(function () {
    // KPI03's qualifying population (normal full-term deliveries) is genuinely rare in recent
    // data — the denominator is often 0. Two distinct, non-overlapping situations get their own
    // message: no qualifying cases at all (neutral) vs. cases exist but none got an antibiotic
    // (a good outcome worth calling out). Separate sessionStorage prefix so this never collides
    // with the fail/warning alert above (which can't fire here — denominator=0 is status 'na',
    // and a 100% result here is 'pass', never 'fail'/'warning').
    const denominator = @json($item['denominator']);
    const numerator    = @json($item['numerator']);
    if (denominator === 0 || (denominator > 0 && numerator === denominator)) {
        const periodKey = @json($ind->code.':'.$year.':'.($quarter ? 'q'.$quarter : $month));
        const seenKey = 'rdu_alert_zeroatb_' + periodKey;
        if (!sessionStorage.getItem(seenKey)) {
            sessionStorage.setItem(seenKey, '1');
            if (denominator === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'ไม่พบข้อมูล',
                    text: 'ไม่พบเคสหญิงคลอดปกติครบกำหนดในช่วงเวลานี้',
                    confirmButtonText: 'รับทราบ',
                    confirmButtonColor: '#2563eb',
                });
            } else {
                Swal.fire({
                    icon: 'success',
                    title: 'ยอดเยี่ยม',
                    text: 'ไม่มีการใช้ยาปฏิชีวนะโดยไม่จำเป็นเลยในกลุ่มหญิงคลอดปกติครบกำหนดช่วงเวลานี้',
                    confirmButtonText: 'รับทราบ',
                    confirmButtonColor: '#059669',
                });
            }
        }
    }
})();
</script>
@endif
<script>
(function () {
    const trend      = @json($trendData);
    const target     = {{ $ind->target_value }};
    const catColor   = @json($meta['trend']);
    const activeMonths = @json($activeMonths);

    const labels = trend.map(d => d.label);
    const values = trend.map(d => d.pct);
    const pointColors = trend.map(d => ({
        pass:    'rgba(16,185,129,0.95)',
        warning: 'rgba(245,158,11,0.95)',
        fail:    'rgba(239,68,68,0.95)',
        na:      'rgba(148,163,184,0.6)',
    }[d.status] ?? 'rgba(59,130,246,0.8)'));
    // Highlight the point(s) matching the currently selected filter period with a blue ring.
    const isActive          = trend.map(d => activeMonths.includes(d.month));
    const pointRadii        = isActive.map(a => a ? 8 : 5);
    const pointHoverRadii   = isActive.map(a => a ? 10 : 7);
    const pointBorderWidths = isActive.map(a => a ? 3 : 2);
    const pointBorderColorsFinal = pointColors.map((c, i) => isActive[i] ? '#2563eb' : c);

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
                    pointBorderColor: pointBorderColorsFinal,
                    pointBorderWidth: pointBorderWidths,
                    fill: true,
                    tension: 0.35,
                    pointRadius: pointRadii,
                    pointHoverRadius: pointHoverRadii,
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
<script>
(function () {
    const canvas = document.getElementById('quarterPieChart');
    if (!canvas) return; // hidden when there's no data at all for the year

    const quarterly = @json($quarterlyBreakdown);
    const pieColors = ['#2563eb', '#7c3aed', '#f59e0b', '#e11d48'];

    const labels = quarterly.map(q => 'ไตรมาส ' + q.quarter);
    const values = quarterly.map(q => q.numerator);
    const total  = values.reduce((a, b) => a + b, 0);

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: pieColors,
                borderColor: '#fff',
                borderWidth: 3,
                borderRadius: 6,
                spacing: 2,
                hoverOffset: 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { display: false }, // custom legend rendered below instead
                tooltip: {
                    titleFont: { family: 'Sarabun, sans-serif' },
                    bodyFont: { family: 'Sarabun, sans-serif' },
                    callbacks: {
                        label: (item) => {
                            const pct = total > 0 ? (item.parsed / total * 100).toFixed(1) : '0.0';
                            return ` ${item.label}: ${item.parsed.toLocaleString()} เคส (${pct}%)`;
                        },
                    },
                },
            },
        },
    });

    // Custom legend — colored dot + label + count + share %, styled to match the rest of the page.
    const legendEl = document.getElementById('quarterPieLegend');
    if (legendEl) {
        legendEl.innerHTML = quarterly.map((q, i) => {
            const pct = total > 0 ? (q.numerator / total * 100).toFixed(1) : '0.0';
            return `
                <div class="flex items-center justify-between text-xs">
                    <span class="flex items-center gap-2 text-slate-600">
                        <span class="inline-block w-2.5 h-2.5 rounded-full" style="background:${pieColors[i]}"></span>
                        ไตรมาส ${q.quarter}
                    </span>
                    <span class="flex items-center gap-2 tabular-nums">
                        <span class="text-slate-400">${q.numerator.toLocaleString()} เคส</span>
                        <span class="font-semibold text-slate-700 w-12 text-right">${pct}%</span>
                    </span>
                </div>`;
        }).join('');
    }
})();
</script>
<script>
(function () {
    const canvas = document.getElementById('drugUsageChart');
    if (!canvas) return; // hidden when there's no antibiotic data for the period

    const drugUsage = @json($drugUsage);
    const labels   = drugUsage.map(d => d.name);
    const counts   = drugUsage.map(d => d.count);
    const percents = drugUsage.map(d => d.percent);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: percents,
                backgroundColor: 'rgba(37,99,235,0.75)',
                borderRadius: 4,
                barThickness: 18,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    titleFont: { family: 'Sarabun, sans-serif' },
                    bodyFont: { family: 'Sarabun, sans-serif' },
                    callbacks: {
                        label: (item) => ` ${counts[item.dataIndex].toLocaleString()} ครั้ง (${item.parsed.x}%)`,
                    },
                },
            },
            scales: {
                x: { min: 0, ticks: { callback: v => v + '%', font: { family: 'Sarabun, sans-serif', size: 11 } }, grid: { color: 'rgba(0,0,0,0.04)' } },
                y: { ticks: { font: { family: 'Sarabun, sans-serif', size: 11 } }, grid: { display: false } },
            },
        },
    });
})();
</script>
<script>
(function () {
    const canvas = document.getElementById('icd10DonutChart');
    if (!canvas) return; // hidden when there's no diagnosis data for the period

    const items  = @json($icd10Ranking['items']);
    const colors = ['#e11d48','#f97316','#eab308','#84cc16','#10b981','#06b6d4','#3b82f6','#8b5cf6','#d946ef','#f43f5e','#94a3b8'];

    const labels = items.map(i => i.code ? (i.code + (i.tname ? ' ' + i.tname : '')) : 'อื่นๆ');
    const values = items.map(i => i.count);
    const total  = values.reduce((a, b) => a + b, 0);

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: colors.slice(0, items.length),
                borderColor: '#fff',
                borderWidth: 3,
                borderRadius: 6,
                spacing: 2,
                hoverOffset: 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { display: false }, // ranked list rendered server-side instead
                tooltip: {
                    titleFont: { family: 'Sarabun, sans-serif' },
                    bodyFont: { family: 'Sarabun, sans-serif' },
                    callbacks: {
                        label: (item) => {
                            const pct = total > 0 ? (item.parsed / total * 100).toFixed(1) : '0.0';
                            return ` ${item.label}: ${item.parsed.toLocaleString()} ครั้ง (${pct}%)`;
                        },
                    },
                },
            },
        },
    });
})();
</script>
<script>
(function () {
    const canvas = document.getElementById('doctorRateChart');
    if (!canvas) return; // hidden when there's no doctor data for the period

    const doctors = @json($doctorRate);

    // Short axis labels (strip the "(license no.)" suffix) so ~28 rotated ticks stay legible —
    // full name + case count still appears in the tooltip title, nothing is lost, just deferred.
    const shortName = (name) => name.replace(/\s*\([^)]*\)\s*$/, '');
    const labels = doctors.map(d => shortName(d.name));
    const counts = doctors.map(d => d.atb); // bar height = case count, not rate — see caption above
    const statusColor = (d) => d.unknown ? '#94a3b8' : (({ pass: '#10b981', warning: '#f59e0b', fail: '#ef4444' })[d.status] ?? '#94a3b8');

    // Soft top-to-bottom gradient per bar (a modern dashboard treatment) instead of flat fills.
    const gradientFor = (ctx, color) => {
        const { chartArea } = ctx.chart;
        if (!chartArea) return color;
        const g = ctx.chart.ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
        g.addColorStop(0, color);
        g.addColorStop(1, color + '99');
        return g;
    };

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: counts,
                backgroundColor: (ctx) => gradientFor(ctx, statusColor(doctors[ctx.dataIndex])),
                hoverBackgroundColor: (ctx) => statusColor(doctors[ctx.dataIndex]),
                borderRadius: { topLeft: 8, topRight: 8, bottomLeft: 0, bottomRight: 0 },
                borderSkipped: false,
                maxBarThickness: 42,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 500, easing: 'easeOutQuart' },
            plugins: {
                legend: { display: false }, // color-coded legend already rendered above the canvas
                tooltip: {
                    titleFont: { family: 'Sarabun, sans-serif', weight: '600' },
                    bodyFont: { family: 'Sarabun, sans-serif' },
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        title: (items) => doctors[items[0].dataIndex].name,
                        label: (item) => {
                            const d = doctors[item.dataIndex];
                            return ` ${d.atb} เคส · อัตรา ${d.rate}% (${d.atb}/${d.total} เคสที่ตรวจ)`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    ticks: {
                        font: { family: 'Sarabun, sans-serif', size: 10.5 },
                        maxRotation: 45,
                        minRotation: 45,
                        autoSkip: false,
                    },
                    grid: { display: false },
                },
                y: {
                    min: 0,
                    ticks: { precision: 0, font: { family: 'Sarabun, sans-serif', size: 11 } },
                    grid: { color: 'rgba(0,0,0,0.045)' },
                },
            },
        },
    });
})();
</script>
@endpush
