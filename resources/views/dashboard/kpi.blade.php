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
       class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 mb-3 cursor-pointer transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded">
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
           class="group bg-white rounded-2xl p-6 shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] cursor-pointer relative overflow-hidden
                  hover:shadow-xl hover:shadow-indigo-100 hover:-translate-y-1 hover:scale-[1.02]
                  hover:ring-2 hover:ring-indigo-200 hover:bg-indigo-50/40
                  transition-all duration-200 ease-out
                  {{ $qIsActive ? 'ring-2 ring-indigo-200' : '' }}">

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
      class="bg-white rounded-2xl px-4 py-3 mb-10 shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)]">
    <input type="hidden" name="quarter" id="quarterInput" value="{{ $quarter ?? '' }}">

    <div class="flex flex-wrap items-center gap-3">
        <span class="msi text-indigo-600">filter_list</span>

        <div class="flex items-center gap-2">
            <label for="filter-year" class="text-xs text-slate-500 font-medium whitespace-nowrap">ปีงบประมาณ</label>
            <select id="filter-year" name="year"
                    class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none cursor-pointer bg-white">
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
                           {{ !$quarter && $month === 0 ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                ทั้งปี
            </button>
            @foreach([1, 2, 3, 4] as $q)
                <button type="button"
                        onclick="document.getElementById('quarterInput').value='{{ $q }}'; this.form.submit();"
                        class="px-3 py-1.5 text-sm font-medium border-l border-slate-300 transition-colors cursor-pointer whitespace-nowrap
                               {{ $quarter === $q ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                    ไตรมาส {{ $q }}
                </button>
            @endforeach
        </div>

        <div class="flex items-center gap-2">
            <label for="filter-month" class="text-xs text-slate-500 font-medium whitespace-nowrap">หรือเลือกเดือน</label>
            <select id="filter-month" name="month"
                    onchange="document.getElementById('quarterInput').value=''"
                    class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none cursor-pointer bg-white">
                <option value="0" @selected(!$quarter && $month === 0)>ทั้งปี</option>
                @foreach($monthOptions as $num => $name)
                    <option value="{{ $num }}" @selected(!$quarter && $num === $month)>{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800
                       text-white rounded-lg text-sm font-medium transition-colors cursor-pointer
                       focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1">
            <span class="msi text-[1rem]">refresh</span>
            รีเฟรช
        </button>

        <div class="ml-auto flex items-center gap-1.5 text-xs text-slate-400">
            <span class="msi text-[.9rem]">history</span>
            คำนวณจาก HOSxP · โหมด Read-Only
        </div>
    </div>

    @if($quarter)
        <div class="mt-2.5 inline-flex items-center gap-1.5 text-xs text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-lg">
            <span class="msi text-[.9rem]">date_range</span>
            กำลังดู {{ \App\Support\FiscalDateRange::quarterLabel($quarter) }} · ปีงบ {{ $year }}
        </div>
    @endif
</form>

{{-- ============ HERO METRIC + TREND ============ --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">

    {{-- Metric Card --}}
    <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] p-7">
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

        {{-- Progress bar with a dashed tick marking the exact target position --}}
        @if($pct !== null)
            <div class="relative w-full bg-slate-100 rounded-full h-2 mt-4 mb-1 overflow-hidden">
                <div class="h-2 rounded-full {{ $barColor }} animate-progress"
                     style="width: {{ min($pct, 100) }}%"
                     role="progressbar"
                     aria-valuenow="{{ $pct }}"
                     aria-valuemin="0"
                     aria-valuemax="100"></div>
                <div class="absolute inset-y-0 border-l-2 border-dashed border-slate-500/70" style="left: {{ min(100, $ind->target_value) }}%;"></div>
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
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 font-semibold text-sm text-slate-800">
                <span class="msi text-indigo-600">trending_up</span>
                แนวโน้มรายเดือน (ปีงบประมาณ {{ $year }})
            </div>
            @if(!empty($activeMonths))
                <span class="inline-flex items-center gap-1.5 text-xs text-indigo-700">
                    <span class="inline-block w-2.5 h-2.5 rounded-full border-2 border-indigo-600 bg-white"></span>
                    ช่วงที่กำลังดูอยู่
                </span>
            @endif
        </div>
        <div class="p-4">
            <div style="height: 360px;">
                <canvas id="trendChart"
                        aria-label="กราฟรายเดือนของ {{ $ind->code }} 2 แผง: แผงบนเป็นผลลัพธ์ร้อยละเทียบกับเป้าหมาย {{ $ind->targetLabel() }} แผงล่างเป็นจำนวน visit ทั้งหมดและจำนวนการใช้ยา"
                        role="img"></canvas>
            </div>
            <p class="mt-2 text-xs text-slate-500">
                แผงบน: ผลลัพธ์ % เทียบเป้าหมาย {{ $ind->targetLabel() }} (เส้นประ) — พื้นเขียว = ผ่านเกณฑ์ · พื้นเหลือง = ใกล้เป้าหมาย · พื้นขาว = ไม่ผ่านเกณฑ์
                <br>แผงล่าง: จำนวน visit และจำนวนการใช้ยา — บอกปริมาณงานเท่านั้น สถานะผ่าน/ไม่ผ่านดูจาก % ในแผงบน
            </p>
        </div>
    </div>
</div>

{{-- ============ CUMULATIVE YTD TREND — shown for every KPI, stub or real ============ --}}
<div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] overflow-hidden mb-10">
    <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-2">
        <span class="msi text-indigo-600">show_chart</span>
        <span class="font-semibold text-sm text-slate-800">แนวโน้มอัตราเฉลี่ยสะสม (ปีงบประมาณ {{ $year }})</span>
    </div>
    <div class="p-4">
        <div style="height: 220px;">
            <canvas id="cumulativeTrendChart" aria-label="กราฟอัตราเฉลี่ยสะสมรายปี" role="img"></canvas>
        </div>
    </div>
</div>

{{-- ============ VISIT PROPORTION DONUT (ได้ยา/ไม่ได้ยา) — shown for every KPI, stub or real ============ --}}
@php
    // KPI03's numerator counts visits that did NOT get an antibiotic (target_direction='higher' — the
    // opposite of every other KPI here) — swap which raw count maps to "ได้ยา"/"ไม่ได้ยา" so the donut's
    // labels stay truthful instead of silently reusing the other KPIs' numerator-is-received assumption.
    $donutReceived = $item['numerator'] !== null && $item['denominator'] !== null
        ? ($ind->target_direction === 'higher' ? $item['denominator'] - $item['numerator'] : $item['numerator'])
        : null;
    $donutNotReceived = $item['numerator'] !== null && $item['denominator'] !== null
        ? $item['denominator'] - $donutReceived
        : null;
@endphp
<div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] p-7 mb-10">
    <div class="flex items-center gap-2 mb-3 font-semibold text-sm text-slate-800">
        <span class="msi text-indigo-600">donut_small</span>
        สัดส่วน VISIT
    </div>
    @if($donutReceived !== null && $item['denominator'] > 0)
        <div class="flex flex-col sm:flex-row items-center gap-6">
            <div class="relative mx-auto flex-shrink-0" style="height: 180px; width: 180px;">
                <canvas id="visitProportionDonut" aria-label="สัดส่วน visit ที่ได้และไม่ได้รับยาปฏิชีวนะ" role="img"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-xl font-bold text-slate-800 tabular-nums">{{ number_format($item['denominator']) }}</span>
                    <span class="text-[.65rem] text-slate-400">visit ทั้งหมด</span>
                </div>
            </div>
            <div id="visitProportionLegend" class="flex-1 w-full flex flex-col gap-2"></div>
        </div>
    @else
        <div class="status-empty py-8">
            <span class="msi">donut_small</span>
            <p class="font-medium text-sm">ยังไม่มีข้อมูลเพียงพอสำหรับช่วงเวลานี้</p>
        </div>
    @endif
</div>

{{-- ============ VISIT STAT CARDS — shown for every KPI, stub or real ============ --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
    <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] p-6">
        <div class="text-xs text-slate-500 mb-1">VISIT ทั้งหมด</div>
        <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ number_format($visitStats['total_visits']) }}</div>
    </div>
    <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] p-6">
        <div class="text-xs text-slate-500 mb-1">VISIT ที่ได้ ATB</div>
        <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ number_format($visitStats['atb_visits']) }}</div>
        @if($visitStats['pct'] !== null)
            <div class="text-[.7rem] text-slate-400">{{ $visitStats['pct'] }}% ของทั้งหมด</div>
        @endif
    </div>
    <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] p-6">
        <div class="text-xs text-slate-500 mb-1">ยาเฉลี่ยต่อ VISIT</div>
        <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ $visitStats['avg_drug_items'] ?? 'N/A' }}</div>
        <div class="text-[.7rem] text-slate-400">ทุกชนิดรวมกัน</div>
    </div>
    <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] p-6">
        <div class="text-xs text-slate-500 mb-1">มูลค่า ATB (บาท)</div>
        <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ number_format($visitStats['atb_cost'], 0) }}</div>
    </div>
    <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] p-6">
        <div class="text-xs text-slate-500 mb-1">ส่วนต่างจากเป้า</div>
        @if($visitStats['gap_from_target'] !== null)
            <div class="text-2xl font-bold tabular-nums {{ $visitStats['gap_is_good'] ? 'text-emerald-600' : 'text-red-600' }}">
                {{ $visitStats['gap_from_target'] > 0 ? '+' : '' }}{{ $visitStats['gap_from_target'] }}%
            </div>
            <div class="text-[.7rem] text-slate-400">เป้า {{ $ind->targetLabel() }}</div>
        @else
            <div class="text-2xl font-bold text-slate-400">N/A</div>
        @endif
    </div>
</div>

{{-- ============ DEEP-DIVE ANALYSIS (KPI01, KPI03, KPI04, KPI05 — any KPI with real detail data) ============ --}}
@if($hasDeepDive)
<div class="section-head">
    <span class="msi">insights</span>
    การวิเคราะห์เชิงลึก
</div>

{{-- Doctor antibiotic rate — table, sorted worst-first by rate. Doctor identity is masked to a
     stable alias (see docs/adr/0002-doctor-identity-masked-in-web-ui-only.md); click/hover the
     alias to reveal the real name. Exports are unaffected — always real names there. --}}
<div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] p-7 mb-10">
    <div class="flex items-center justify-between gap-3 mb-1 flex-wrap">
        <div class="flex items-center gap-2 font-semibold text-sm text-slate-800">
            <span class="msi text-indigo-600">medical_services</span>
            อัตราการสั่งยาปฏิชีวนะรายแพทย์ผู้ตรวจ
        </div>
        @if(count($doctorRate) > 0)
            <div class="flex items-center gap-3 text-[.7rem] text-slate-500 flex-wrap">
                <span class="flex items-center gap-1"><span class="inline-block w-2.5 h-2.5 rounded-full" style="background:#ef4444"></span>เกินเกณฑ์ {{ $doctorRateThreshold }}%</span>
                <span class="flex items-center gap-1"><span class="inline-block w-2.5 h-2.5 rounded-full" style="background:#10b981"></span>ต่ำกว่าเกณฑ์</span>
            </div>
        @endif
    </div>
    @if(count($doctorRate) > 0)
        @php
            $gradedDoctors   = array_values(array_filter($doctorRate, fn ($r) => $r['graded']));
            $ungradedDoctors = array_values(array_filter($doctorRate, fn ($r) => ! $r['graded'] && ! $r['unknown']));
            $unknownRow      = collect($doctorRate)->firstWhere('unknown', true);
        @endphp

        <div class="flex items-start gap-2.5 mb-4 p-3 rounded-lg bg-indigo-50 border border-indigo-100">
            <span class="msi text-indigo-500 text-[1.1rem] flex-shrink-0 mt-0.5">info</span>
            <p class="text-sm text-indigo-900 leading-relaxed">
                <strong>ชื่อแพทย์แสดงเป็นรหัสปกปิด</strong>
                <span class="text-indigo-700">(กด/hover ที่รหัสเพื่อดูชื่อจริง)</span>
                &nbsp;·&nbsp; เรียงจาก<strong>อัตราสูงสุด</strong>ไปต่ำสุด
                &nbsp;·&nbsp; แพทย์ที่มี visit น้อยกว่า {{ $doctorRateMinVisits }} ครั้งจะไม่ตัดสินผ่าน/ไม่ผ่านเกณฑ์ (ข้อมูลไม่พอ)
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-slate-500 border-b border-slate-200">
                        <th class="py-2 pr-3 font-medium">แพทย์</th>
                        <th class="py-2 pr-3 font-medium text-right">VISIT</th>
                        <th class="py-2 pr-3 font-medium text-right">ได้ ATB</th>
                        <th class="py-2 pr-3 font-medium text-right">อัตรา</th>
                        <th class="py-2 font-medium">เทียบเป้า {{ $doctorRateThreshold }}%</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($gradedDoctors as $row)
                        <tr class="border-b border-slate-100 last:border-0">
                            <td class="py-2 pr-3">
                                <span class="doctor-alias cursor-pointer select-none font-medium text-slate-700 hover:text-indigo-600 transition-colors"
                                      data-code="{{ $row['code'] }}" data-real="{{ $row['real_name'] }}"
                                      title="{{ $row['real_name'] }}"
                                      onclick="this.textContent = this.textContent.trim() === this.dataset.code ? this.dataset.real : this.dataset.code">{{ $row['code'] }}</span>
                            </td>
                            <td class="py-2 pr-3 text-right tabular-nums text-slate-500">{{ number_format($row['total']) }}</td>
                            <td class="py-2 pr-3 text-right tabular-nums text-slate-500">{{ number_format($row['atb']) }}</td>
                            <td class="py-2 pr-3 text-right tabular-nums font-semibold {{ $row['red'] ? 'text-red-600' : 'text-emerald-600' }}">{{ $row['rate'] }}%</td>
                            <td class="py-2">
                                <div class="relative h-3 bg-slate-100 rounded-full overflow-hidden" style="min-width:120px;">
                                    <div class="absolute inset-y-0 left-0 rounded-full {{ $row['red'] ? 'bg-red-500' : 'bg-emerald-500' }}" style="width: {{ min(100, $row['rate']) }}%;"></div>
                                    <div class="absolute inset-y-0 border-l-2 border-dashed border-slate-400/70" style="left: {{ min(100, $doctorRateThreshold) }}%;"></div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if(count($ungradedDoctors) > 0 || $unknownRow)
            <div class="mt-4 pt-3 border-t border-dashed border-slate-200">
                <p class="text-xs text-slate-400 mb-2 flex items-center gap-1">
                    <span class="msi text-[.9rem]">info</span>
                    ข้อมูลไม่เพียงพอสำหรับตัดสินผ่าน/ไม่ผ่านเกณฑ์ (visit น้อยกว่า {{ $doctorRateMinVisits }} ครั้ง หรือไม่ระบุแพทย์) — แสดงไว้เพื่อความครบถ้วน
                </p>
                <table class="w-full text-sm opacity-70">
                    <tbody>
                        @foreach($ungradedDoctors as $row)
                            <tr class="border-b border-slate-100 last:border-0">
                                <td class="py-1.5 pr-3 w-1/5">
                                    <span class="doctor-alias cursor-pointer select-none text-slate-500"
                                          data-code="{{ $row['code'] }}" data-real="{{ $row['real_name'] }}"
                                          title="{{ $row['real_name'] }}"
                                          onclick="this.textContent = this.textContent.trim() === this.dataset.code ? this.dataset.real : this.dataset.code">{{ $row['code'] }}</span>
                                </td>
                                <td class="py-1.5 pr-3 text-right tabular-nums text-slate-400">{{ $row['total'] }}</td>
                                <td class="py-1.5 pr-3 text-right tabular-nums text-slate-400">{{ $row['atb'] }}</td>
                                <td class="py-1.5 text-right tabular-nums text-slate-400">{{ $row['rate'] }}%</td>
                            </tr>
                        @endforeach
                        @if($unknownRow)
                            <tr>
                                <td class="py-1.5 pr-3 text-slate-400">ไม่ระบุแพทย์</td>
                                <td class="py-1.5 pr-3 text-right tabular-nums text-slate-400">{{ $unknownRow['total'] }}</td>
                                <td class="py-1.5 pr-3 text-right tabular-nums text-slate-400">{{ $unknownRow['atb'] }}</td>
                                <td class="py-1.5 text-right tabular-nums text-slate-400">{{ $unknownRow['rate'] }}%</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endif
    @else
        <div class="status-empty py-8">
            <span class="msi">medical_services</span>
            <p class="font-medium text-sm">ยังไม่มีข้อมูลแพทย์ในช่วงเวลานี้</p>
        </div>
    @endif
</div>

{{-- ICD-10 ranking + drug usage, paired side by side — deliberately distinct header/icon
     from the quarterly donut further down so the two never get confused --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
    <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] p-7">
        <div class="flex items-center gap-2 mb-3 font-semibold text-sm text-slate-800">
            <span class="msi text-indigo-600">local_hospital</span>
            การวินิจฉัย ICD-10 ที่พบบ่อย 10 อันดับแรก
        </div>
        @if(count($icd10Ranking['items']) > 0)
            <div style="height: {{ max(220, count($icd10Ranking['items']) * 32) }}px;">
                <canvas id="icd10DonutChart" aria-label="การวินิจฉัย ICD-10 ที่พบบ่อย 10 อันดับแรก" role="img"></canvas>
            </div>
        @else
            <div class="status-empty py-8">
                <span class="msi">local_hospital</span>
                <p class="font-medium text-sm">ยังไม่มีข้อมูลการวินิจฉัยในช่วงเวลานี้</p>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] p-7">
        <div class="flex items-center gap-2 mb-3 font-semibold text-sm text-slate-800">
            <span class="msi text-indigo-600">bar_chart</span>
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

{{-- ============ COVERAGE-TYPE BREAKDOWN (สิทธิ์การรักษา) ============ --}}
<div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] p-7 mb-10">
    <div class="flex items-center gap-2 mb-3 font-semibold text-sm text-slate-800">
        <span class="msi text-indigo-600">badge</span>
        สิทธิ์การรักษา
    </div>
    @if(count($coverageBreakdown) > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-slate-500 border-b border-slate-200">
                        <th class="py-2 pr-3 font-medium">สิทธิ์การรักษา</th>
                        <th class="py-2 pr-3 font-medium text-right">VISIT</th>
                        <th class="py-2 pr-3 font-medium text-right">ได้ ATB</th>
                        <th class="py-2 font-medium text-right">% ได้ ATB</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($coverageBreakdown as $row)
                        <tr class="border-b border-slate-100 last:border-0">
                            <td class="py-2 pr-3 text-slate-700">{{ $row['coverage'] }}</td>
                            <td class="py-2 pr-3 text-right tabular-nums text-slate-500">{{ number_format($row['total']) }}</td>
                            <td class="py-2 pr-3 text-right tabular-nums text-slate-500">{{ number_format($row['atb']) }}</td>
                            <td class="py-2 text-right tabular-nums font-semibold text-slate-700">{{ $row['rate'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="status-empty py-8">
            <span class="msi">badge</span>
            <p class="font-medium text-sm">ยังไม่มีข้อมูลในช่วงเวลานี้</p>
        </div>
    @endif
</div>

{{-- ============ DEPARTMENT + ROOM/WARD BREAKDOWN (ATB-positive visits only) ============ --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
    <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] p-7">
        <div class="flex items-center gap-2 mb-3 font-semibold text-sm text-slate-800">
            <span class="msi text-indigo-600">local_hospital</span>
            แผนก (เฉพาะ VISIT ที่ได้ ATB)
        </div>
        @if(count($departmentBreakdown) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody>
                        @foreach($departmentBreakdown as $row)
                            <tr class="border-b border-slate-100 last:border-0">
                                <td class="py-1.5 pr-3 text-slate-700">{{ $row['label'] }}</td>
                                <td class="py-1.5 text-right tabular-nums font-semibold text-slate-700">{{ number_format($row['count']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="status-empty py-8">
                <span class="msi">local_hospital</span>
                <p class="font-medium text-sm">ยังไม่มีข้อมูลในช่วงเวลานี้</p>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] p-7">
        <div class="flex items-center gap-2 mb-3 font-semibold text-sm text-slate-800">
            <span class="msi text-indigo-600">meeting_room</span>
            {{ $ind->code === 'KPI03' ? 'หอผู้ป่วย (เฉพาะ VISIT ที่ได้ ATB)' : 'ห้องตรวจ (เฉพาะ VISIT ที่ได้ ATB)' }}
        </div>
        @if(count($roomBreakdown) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody>
                        @foreach($roomBreakdown as $row)
                            <tr class="border-b border-slate-100 last:border-0">
                                <td class="py-1.5 pr-3 text-slate-700">{{ $row['label'] }}</td>
                                <td class="py-1.5 text-right tabular-nums font-semibold text-slate-700">{{ number_format($row['count']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="status-empty py-8">
                <span class="msi">meeting_room</span>
                <p class="font-medium text-sm">ยังไม่มีข้อมูลในช่วงเวลานี้</p>
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
    <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] p-7">
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
        <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,.04),0_1px_8px_rgba(0,0,0,.03)] p-7">
            <div class="flex items-center gap-2 mb-3 font-semibold text-sm text-slate-800">
                <span class="msi text-indigo-600">description</span>
                คำนิยามตัวชี้วัด
            </div>
            <div class="space-y-3 text-sm">
                @if($ind->numerator_desc)
                    <div class="p-3 bg-indigo-50 rounded-lg text-indigo-800">
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
                    confirmButtonColor: '#4F46E5',
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
@php
    $numeratorLabel = match ($ind->code) {
        'KPI01', 'KPI04', 'KPI05' => 'visit ที่ได้รับยาปฏิชีวนะ',
        'KPI03'                   => 'visit ที่ไม่ได้รับยาปฏิชีวนะ',
        default                   => 'ตัวตั้งของตัวชี้วัด',
    };
@endphp
<script>
(function () {
    const trend      = @json($trendData);
    const target     = {{ $ind->target_value }};
    const direction  = @json($ind->target_direction);
    const nearBand   = {{ \App\Services\RduCalculationService::NEAR_THRESHOLD }};
    const catColor   = @json($meta['trend']);
    const activeMonths = @json($activeMonths);
    const numLabel   = @json($numeratorLabel);
    const fontSans   = window.RDU_FONT_SANS || 'Sarabun, sans-serif';
    const fontPx     = px => Math.round(px * (window.RDU_FONT_SCALE || 1));

    const labels      = trend.map(d => d.label);
    const visitCounts = trend.map(d => d.den ?? 0);
    const useCounts   = trend.map(d => d.num ?? 0);
    // Skip months with no visits instead of plotting 0% — a stored month with no visits comes back
    // with pct = 0 rather than null, so test the denominator.
    const values      = trend.map(d => d.den ? d.pct : null);
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
    const pointBorderColorsFinal = pointColors.map((c, i) => isActive[i] ? '#4F46E5' : c);

    // Status zones behind the % panel, mirroring RduCalculationService::getStatus(): the pass zone runs
    // from the target to the good end of the scale, the near zone is the NEAR_THRESHOLD band just past it,
    // and the rest is the (unshaded) fail zone.
    const clampPct = v => Math.min(100, Math.max(0, v));
    const higher   = direction === 'higher';
    const nearEdge = clampPct(higher ? target - nearBand : target + nearBand);
    const zones = {
        pass: { from: higher ? target : 0, to: higher ? 100 : target, fill: 'rgba(16,185,129,0.09)', label: 'ผ่านเกณฑ์' },
        near: { from: Math.min(target, nearEdge), to: Math.max(target, nearEdge), fill: 'rgba(245,158,11,0.12)', label: 'ใกล้เป้าหมาย' },
        fail: { from: higher ? 0 : nearEdge, to: higher ? nearEdge : 100, fill: null, label: 'ไม่ผ่านเกณฑ์' },
    };
    const statusZones = {
        id: 'statusZones',
        beforeDatasetsDraw(chart) {
            const { ctx, chartArea, scales: { y } } = chart;
            const labelH = fontPx(11) + 4;
            const labelX = chartArea.right - 6;
            const span = z => ({ top: y.getPixelForValue(z.to), bottom: y.getPixelForValue(z.from) });
            const text = (label, py, baseline) => { ctx.textBaseline = baseline; ctx.fillText(label, labelX, py); };
            ctx.save();
            ctx.font = `${fontPx(11)}px ${fontSans}`;
            ctx.textAlign = 'right';
            [zones.pass, zones.near].forEach(z => {
                const { top, bottom } = span(z);
                if (bottom > top) {
                    ctx.fillStyle = z.fill;
                    ctx.fillRect(chartArea.left, top, chartArea.right - chartArea.left, bottom - top);
                }
            });
            ctx.fillStyle = '#64748b';
            // Pass: label centred when the band is tall enough (a very low 'lower' target leaves no room).
            const pass = span(zones.pass);
            if (pass.bottom - pass.top >= labelH) text(zones.pass.label, (pass.top + pass.bottom) / 2, 'middle');
            // Near: the 5-point band is usually thinner than a line of text, so its label sits just
            // outside the band on the fail side instead of being dropped.
            const near = span(zones.near);
            const nearFits = near.bottom - near.top >= labelH;
            if (near.bottom > near.top) {
                if (nearFits) text(zones.near.label, (near.top + near.bottom) / 2, 'middle');
                else if (higher) text(zones.near.label, near.bottom + 2, 'top');
                else text(zones.near.label, near.top - 2, 'bottom');
            }
            // Fail: unshaded, labelled at its far end so it never meets the near label.
            const fail = span(zones.fail);
            if (fail.bottom - fail.top >= labelH * (nearFits ? 1 : 2)) {
                if (higher) text(zones.fail.label, fail.bottom - 2, 'bottom');
                else text(zones.fail.label, fail.top + 2, 'top');
            }
            // Divider between the % panel (top) and the count panel (bottom).
            ctx.strokeStyle = '#cbd5e1';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(chartArea.left, y.bottom + 0.5);
            ctx.lineTo(chartArea.right, y.bottom + 0.5);
            ctx.stroke();
            ctx.restore();
        },
    };

    new Chart(document.getElementById('trendChart'), {
        plugins: [statusZones],
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    key: 'visit',
                    label: 'จำนวน visit ทั้งหมด',
                    data: visitCounts,
                    backgroundColor: 'rgba(148,163,184,0.45)',
                    borderRadius: 4,
                    yAxisID: 'y1',
                    order: 4,
                },
                {
                    type: 'bar',
                    key: 'use',
                    label: numLabel,
                    data: useCounts,
                    backgroundColor: 'rgba(79,70,229,0.75)',
                    borderRadius: 4,
                    yAxisID: 'y1',
                    order: 3,
                },
                {
                    type: 'line',
                    key: 'result',
                    label: 'ผลลัพธ์ (%)',
                    data: values,
                    borderColor: catColor,
                    backgroundColor: 'transparent',
                    pointBackgroundColor: pointColors,
                    pointBorderColor: pointBorderColorsFinal,
                    pointBorderWidth: pointBorderWidths,
                    tension: 0.35,
                    pointRadius: pointRadii,
                    pointHoverRadius: pointHoverRadii,
                    spanGaps: true,
                    borderWidth: 2.5,
                    clip: 10, // let a point at exactly 0% or 100% draw fully instead of being cut at the edge
                    yAxisID: 'y',
                    order: 1,
                },
                {
                    type: 'line',
                    key: 'target',
                    label: 'เป้าหมาย (' + target + '%)',
                    data: labels.map(() => target),
                    // Neutral slate, not indigo — so the eye does not pair the target with the indigo drug-use bars.
                    borderColor: 'rgba(71,85,105,0.9)',
                    backgroundColor: 'transparent',
                    borderDash: [6, 4],
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    borderWidth: 2,
                    yAxisID: 'y',
                    order: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { font: { family: fontSans, size: fontPx(12) } } },
                tooltip: {
                    titleFont: { family: fontSans },
                    bodyFont:  { family: fontSans },
                    callbacks: {
                        label: (item) => {
                            const d = trend[item.dataIndex];
                            switch (item.dataset.key) {
                                case 'visit':  return ` จำนวน visit ทั้งหมด: ${(d.den ?? 0).toLocaleString()}`;
                                case 'use':    return ` ${numLabel}: ${(d.num ?? 0).toLocaleString()}`;
                                case 'target': return ` เป้าหมาย: ${target}%`;
                                default:
                                    if (!d.den) return ' ผลลัพธ์: ไม่มีข้อมูล';
                                    return ` ผลลัพธ์: ${d.pct}% (${d.num ?? '-'} / ${d.den ?? '-'})`;
                            }
                        },
                    },
                },
            },
            // Two stacked panels sharing the month axis, instead of two y-scales overlaid in one plot:
            // bars on a count scale never share space with the % target line. Same-side scales in one
            // stack are laid out top-down by descending `weight`, so `y` (weight 1) is the upper panel.
            scales: {
                x: { ticks: { font: { family: fontSans, size: fontPx(11) } }, grid: { color: 'rgba(0,0,0,0.04)' } },
                y: {
                    type: 'linear', position: 'left', stack: 'trend', stackWeight: 3, weight: 1,
                    min: 0, max: 100,
                    title: { display: true, text: 'ผลลัพธ์ (%)', font: { family: fontSans, size: fontPx(11) } },
                    ticks: { callback: v => v + '%', font: { family: fontSans, size: fontPx(11) } },
                    grid: { color: 'rgba(0,0,0,0.05)' },
                },
                y1: {
                    type: 'linear', position: 'left', stack: 'trend', stackWeight: 2, weight: 0,
                    min: 0, suggestedMax: Math.max(1, ...visitCounts) * 1.1,
                    title: { display: true, text: 'จำนวน visit', font: { family: fontSans, size: fontPx(11) } },
                    // Blank the top tick label so it does not collide with the 0% label of the panel above.
                    ticks: {
                        precision: 0,
                        callback: (v, i, ticks) => i === ticks.length - 1 ? '' : v.toLocaleString(),
                        font: { family: fontSans, size: fontPx(11) },
                    },
                    grid: { color: 'rgba(0,0,0,0.05)' },
                },
            },
        },
    });
})();
</script>
<script>
(function () {
    const canvas = document.getElementById('cumulativeTrendChart');
    if (!canvas) return;

    const trend  = @json($trendData);
    const target = {{ $ind->target_value }};
    const labels = trend.map(d => d.label);

    // Running cumulative rate from the start of the fiscal year through each month — sum(num)/sum(den)
    // as of that month. A month with den=0 contributes nothing to either accumulator, so the cumulative
    // value naturally holds flat at the prior month's level rather than needing special-case handling.
    let cumNum = 0, cumDen = 0;
    const cumPct = trend.map(d => {
        cumNum += d.num ?? 0;
        cumDen += d.den ?? 0;
        return cumDen > 0 ? Math.round((cumNum / cumDen) * 1000) / 10 : null;
    });

    new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'อัตราเฉลี่ยสะสม (%)',
                    data: cumPct,
                    borderColor: '#4F46E5',
                    backgroundColor: 'rgba(79,70,229,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#4F46E5',
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
                legend: { position: 'top', labels: { font: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif'), size: Math.round(12 * (window.RDU_FONT_SCALE || 1)) } } },
                tooltip: {
                    titleFont: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif') },
                    bodyFont: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif') },
                    callbacks: {
                        label: (item) => item.datasetIndex === 1
                            ? ` เป้าหมาย: ${item.parsed.y}%`
                            : ` สะสมนับตั้งแต่ต้นปีงบถึงเดือนนี้: ${item.parsed.y === null ? 'ไม่มีข้อมูล' : item.parsed.y + '%'}`,
                    },
                },
            },
            scales: {
                x: { ticks: { font: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif'), size: Math.round(11 * (window.RDU_FONT_SCALE || 1)) } }, grid: { color: 'rgba(0,0,0,0.04)' } },
                y: { min: 0, max: 100, ticks: { callback: v => v + '%', font: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif'), size: Math.round(11 * (window.RDU_FONT_SCALE || 1)) } }, grid: { color: 'rgba(0,0,0,0.05)' } },
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
    const pieColors = ['#4F46E5', '#818CF8', '#C7D2FE', '#E0E7FF'];

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
                    titleFont: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif') },
                    bodyFont: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif') },
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
    const canvas = document.getElementById('visitProportionDonut');
    if (!canvas) return; // hidden when there's no denominator data for the period

    const received    = {{ $donutReceived ?? 0 }};
    const notReceived = {{ $donutNotReceived ?? 0 }};
    const total       = received + notReceived;
    const colors      = ['#4F46E5', '#e2e8f0'];
    const labels      = ['ได้ยาปฏิชีวนะ', 'ไม่ได้ยาปฏิชีวนะ'];
    const values      = [received, notReceived];

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
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
            cutout: '70%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    titleFont: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif') },
                    bodyFont: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif') },
                    callbacks: {
                        label: (item) => {
                            const pct = total > 0 ? (item.parsed / total * 100).toFixed(1) : '0.0';
                            return ` ${item.label}: ${item.parsed.toLocaleString()} visit (${pct}%)`;
                        },
                    },
                },
            },
        },
    });

    const legendEl = document.getElementById('visitProportionLegend');
    if (legendEl) {
        legendEl.innerHTML = labels.map((label, i) => {
            const pct = total > 0 ? (values[i] / total * 100).toFixed(1) : '0.0';
            return `
                <div class="flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2 text-slate-600">
                        <span class="inline-block w-2.5 h-2.5 rounded-full" style="background:${colors[i]}"></span>
                        ${label}
                    </span>
                    <span class="flex items-center gap-2 tabular-nums">
                        <span class="text-slate-400">${values[i].toLocaleString()} visit</span>
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
                backgroundColor: 'rgba(79,70,229,0.75)',
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
                    titleFont: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif') },
                    bodyFont: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif') },
                    callbacks: {
                        label: (item) => ` ${counts[item.dataIndex].toLocaleString()} ครั้ง (${item.parsed.x}%)`,
                    },
                },
            },
            scales: {
                x: { min: 0, ticks: { callback: v => v + '%', font: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif'), size: Math.round(11 * (window.RDU_FONT_SCALE || 1)) } }, grid: { color: 'rgba(0,0,0,0.04)' } },
                y: { ticks: { font: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif'), size: Math.round(11 * (window.RDU_FONT_SCALE || 1)) } }, grid: { display: false } },
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

    const labels = items.map(i => i.code ? (i.code + (i.tname ? ' ' + i.tname : '')) : 'อื่นๆ');
    const values = items.map(i => i.count);
    const total  = values.reduce((a, b) => a + b, 0);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: 'rgba(79,70,229,0.75)',
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
                    titleFont: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif') },
                    bodyFont: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif') },
                    callbacks: {
                        label: (item) => {
                            const pct = total > 0 ? (item.parsed.x / total * 100).toFixed(1) : '0.0';
                            return ` ${item.parsed.x.toLocaleString()} ครั้ง (${pct}%)`;
                        },
                    },
                },
            },
            scales: {
                x: { min: 0, ticks: { font: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif'), size: Math.round(11 * (window.RDU_FONT_SCALE || 1)) } }, grid: { color: 'rgba(0,0,0,0.04)' } },
                y: { ticks: { font: { family: (window.RDU_FONT_SANS || 'Sarabun, sans-serif'), size: Math.round(11 * (window.RDU_FONT_SCALE || 1)) } }, grid: { display: false } },
            },
        },
    });
})();
</script>
@endpush
