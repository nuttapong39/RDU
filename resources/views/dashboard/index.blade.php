@extends('layouts.dashboard')

@section('title', 'ภาพรวม '.$kpiData->where('status', '!=', 'not_configured')->count().' ตัวชี้วัด RDU')

@section('content')

@php
    $catIcons = [
        'Antibiotic'     => 'medication',
        'DrugQuality'    => 'prescriptions',
        'PatientSafety'  => 'health_and_safety',
        'ChronicDisease' => 'bloodtype',
    ];

    // Colour is reserved for status (pass/warning/fail/na) — nothing else on this page is coloured.
    $statusMeta = [
        'pass'    => ['label' => 'ผ่านเกณฑ์',    'dot' => 'bg-emerald-500', 'text' => 'text-emerald-600', 'bar' => 'bg-emerald-500'],
        'warning' => ['label' => 'ใกล้เป้าหมาย', 'dot' => 'bg-amber-500',   'text' => 'text-amber-600',   'bar' => 'bg-amber-500'],
        'fail'    => ['label' => 'ไม่ผ่านเกณฑ์', 'dot' => 'bg-red-500',     'text' => 'text-red-600',     'bar' => 'bg-red-500'],
        'na'      => ['label' => 'ไม่มีข้อมูล',   'dot' => 'bg-slate-400',   'text' => 'text-slate-400',   'bar' => 'bg-slate-300'],
        // Not a verdict: shown beside the counts, never inside them (CONTEXT.md: ยังไม่ตั้งค่า).
        'not_configured' => ['label' => 'ยังไม่ตั้งค่า', 'dot' => 'bg-slate-300', 'text' => 'text-slate-500', 'bar' => 'bg-slate-200'],
    ];
@endphp

{{-- ============ HERO ============ --}}
<section class="rdu-hero mb-4" aria-labelledby="hero-title">
    <div class="flex flex-wrap items-center gap-x-6 gap-y-4">
        <div class="flex items-center gap-4 min-w-0 flex-1 basis-72">
            {{-- RDU mark: a capsule with a check — the right drug, used rightly --}}
            <svg viewBox="0 0 64 64" class="w-14 h-14 md:w-16 md:h-16 flex-shrink-0" role="img"
                 aria-label="สัญลักษณ์ RDU: แคปซูลยากับเครื่องหมายถูก">
                <defs>
                    <linearGradient id="rdu-hero-bg" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#1e3a8a"/>
                        <stop offset=".55" stop-color="#2563eb"/>
                        <stop offset="1" stop-color="#059669"/>
                    </linearGradient>
                </defs>
                <rect width="64" height="64" rx="16" fill="url(#rdu-hero-bg)"/>
                <g transform="rotate(-40 29 29)">
                    <path d="M19 21h10v16H19a8 8 0 0 1 0-16z" fill="#fff"/>
                    <path d="M29 21h10a8 8 0 0 1 0 16H29z" fill="#fff" fill-opacity=".5"/>
                </g>
                <circle cx="46" cy="46" r="10" fill="#10b981" stroke="#fff" stroke-width="3"/>
                <path d="M41.5 46.2l3 3 6-6.2" fill="none" stroke="#fff" stroke-width="2.6"
                      stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <div class="min-w-0">
                <h1 id="hero-title" class="text-xl md:text-2xl font-bold text-slate-900 leading-tight">RDU Dashboard</h1>
                <p class="text-sm text-slate-600 mt-0.5">การใช้ยาอย่างสมเหตุสมผล (Rational Drug Use){{ $facility['configured'] ? ' · '.$facility['name'] : '' }}</p>
                <p class="text-xs text-slate-400 mt-0.5">ข้อมูลจาก HOSxP · อัปเดตทุก 1 ชั่วโมง</p>
            </div>
        </div>

        <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-center gap-2">
            <label for="filter-year" class="sr-only">ปีงบประมาณ</label>
            <select id="filter-year" name="year" class="rdu-select">
                @foreach($yearOptions as $y)
                    <option value="{{ $y }}" @selected($y === $year)>ปีงบ {{ $y }}</option>
                @endforeach
            </select>

            <label for="filter-month" class="sr-only">เดือน</label>
            <select id="filter-month" name="month" class="rdu-select">
                <option value="0" @selected($month === 0)>ทั้งปี</option>
                @foreach($monthOptions as $num => $name)
                    <option value="{{ $num }}" @selected($num === $month)>{{ $name }}</option>
                @endforeach
            </select>

            <button type="submit"
                    class="rdu-btn inline-flex items-center gap-1.5 px-3 py-1.5 border border-slate-300 bg-white hover:bg-slate-50
                           text-slate-700 rounded-lg text-sm font-medium transition-colors cursor-pointer
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1">
                <span class="msi text-[1rem]" aria-hidden="true">refresh</span>
                รีเฟรช
            </button>
        </form>
    </div>

    <dl class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap gap-x-6 gap-y-2">
        @foreach($statusMeta as $key => $meta)
            @continue($key === 'not_configured')
            <div class="flex items-center gap-2 {{ $summary[$key] === 0 ? 'opacity-50' : '' }}">
                <span class="w-2 h-2 rounded-full {{ $meta['dot'] }}" aria-hidden="true"></span>
                <dt class="text-xs text-slate-500">{{ $meta['label'] }}</dt>
                <dd class="text-lg font-bold tabular-nums leading-none {{ $summary[$key] === 0 ? 'text-slate-400' : $meta['text'] }}"
                    data-status-count="{{ $key }}">{{ $summary[$key] }}</dd>
            </div>
        @endforeach
        @if(($summary['not_configured'] ?? 0) > 0)
            <div class="flex items-center gap-2" data-not-configured-count>
                <span class="w-2 h-2 rounded-full bg-slate-300 ring-1 ring-slate-400" aria-hidden="true"></span>
                <dt class="text-xs text-slate-500">ยังไม่ตั้งค่า</dt>
                <dd class="text-lg font-bold tabular-nums leading-none text-slate-500">{{ $summary['not_configured'] }}</dd>
                <a href="{{ route('drug-code-sets.index') }}" class="text-xs text-indigo-600 hover:underline">ตั้งค่ารหัสยา</a>
            </div>
        @endif
    </dl>
</section>

{{-- ============ ต้องดูก่อน — every KPI short of its target, failures first ============ --}}
@if($attention->isNotEmpty())
    <section data-attention class="mb-6 flex flex-wrap items-center gap-2" aria-label="ตัวชี้วัดที่ต้องดูก่อน">
        <span class="text-xs font-semibold text-slate-500 mr-1">ต้องดูก่อน</span>
        @foreach($attention as $a)
            @php $ind = $a['indicator']; @endphp
            <a href="{{ route('dashboard.kpi', $ind->code) }}" data-attention-item
               class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition-colors
                      focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500
                      {{ $a['status'] === 'fail' ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100'
                                                 : 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100' }}"
               title="{{ $ind->name }} — {{ $statusMeta[$a['status']]['label'] }}">
                <span class="font-mono font-semibold">{{ $ind->code }}</span>
                <span class="font-bold tabular-nums">{{ $a['percentage'] }}%</span>
                <span class="opacity-75">(เป้า {{ $ind->targetLabel() }})</span>
                @if($a['small_sample'])
                    <span class="small-sample">เคสน้อย · n={{ $a['denominator'] }}</span>
                @endif
            </a>
        @endforeach
    </section>
@endif

{{-- ============ KPI GRID (grouped by category) ============ --}}
@foreach($categories as $catKey => $catLabel)
    @if($kpiByCategory->has($catKey))
        <div id="cat-{{ Str::slug($catKey) }}" class="section-head">
            <span class="msi" aria-hidden="true">{{ $catIcons[$catKey] ?? 'category' }}</span>
            หมวด{{ $catLabel }} ({{ count($kpiByCategory[$catKey]) }} ตัวชี้วัด)
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">
            @foreach($kpiByCategory[$catKey] as $item)
                @php
                    $ind    = $item['indicator'];
                    $pct    = $item['percentage'];
                    $status = $item['status'];
                    $meta   = $statusMeta[$status] ?? $statusMeta['na'];
                    $unit   = $ind->countUnit();
                @endphp

                @if($status === 'not_configured')
                <a href="{{ route('dashboard.kpi', $ind->code) }}" data-kpi-card="{{ $ind->code }}" data-not-configured
                   class="kpi-card-v2 status-na">
                    <span class="msi kpi-watermark" aria-hidden="true">{{ $catIcons[$catKey] ?? 'category' }}</span>
                    <div class="flex items-center gap-1.5">
                        <span class="kpi-code">{{ $ind->code }}</span>
                        <span class="ml-auto text-[.7rem] font-semibold text-slate-500">ยังไม่ตั้งค่า</span>
                    </div>
                    <p class="text-[.78rem] text-slate-600 leading-snug line-clamp-2 min-h-[2.4rem]">{{ $ind->name }}</p>
                    <p class="text-sm text-slate-500">ยังไม่ได้ตั้งค่ารหัสยาของตัวชี้วัดนี้</p>
                    <div class="text-[.7rem] text-slate-400">เป้า {{ $ind->targetLabel() }}</div>
                </a>
                @continue
                @endif

                <a href="{{ route('dashboard.kpi', $ind->code) }}" data-kpi-card="{{ $ind->code }}"
                   class="kpi-card-v2 status-{{ $status }}">
                    <span class="msi kpi-watermark" aria-hidden="true">{{ $catIcons[$catKey] ?? 'category' }}</span>

                    <div class="flex items-center gap-1.5">
                        <span class="kpi-code">{{ $ind->code }}</span>
                        @if(($item['source'] ?? 'hosxp') === 'hdc')
                            @if($item['hdc_error'] ?? false)
                                <span class="text-[.65rem] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-700"
                                      title="ยังไม่มี query HOSxP และดึงข้อมูลจาก HDC ไม่ได้ในขณะนี้">HDC ไม่ตอบสนอง</span>
                            @else
                                <span class="text-[.65rem] font-bold px-1.5 py-0.5 rounded bg-hdc/10 text-hdc-ink"
                                      title="ยังไม่มี query HOSxP — แสดงยอดรวมทั้งปีงบจาก HDC (ข้อมูลส่วนกลาง)">HDC · ทั้งปี</span>
                            @endif
                        @endif
                        <span class="ml-auto text-[.7rem] font-semibold {{ $meta['text'] }}">{{ $meta['label'] }}</span>
                    </div>

                    <p class="text-[.78rem] text-slate-600 leading-snug line-clamp-2 min-h-[2.4rem]">{{ $ind->name }}</p>

                    <div class="flex items-baseline flex-wrap gap-x-2 gap-y-1">
                        <span class="text-3xl font-bold tabular-nums leading-none {{ $meta['text'] }}">
                            {{ $pct !== null ? $pct.'%' : '—' }}
                        </span>
                        @if($item['small_sample'])
                            <span class="small-sample" title="ตัวหารน้อยกว่า {{ $smallSampleMin }} — ร้อยละแกว่งได้มากจากเคสเดียว">เคสน้อย · n={{ $item['denominator'] }}</span>
                        @endif
                    </div>

                    {{-- Bar with a tick at the target --}}
                    <div class="relative h-1.5 rounded-full bg-slate-100">
                        @if($pct !== null)
                            <div class="h-1.5 rounded-full animate-progress {{ $meta['bar'] }}"
                                 style="width: {{ min($pct, 100) }}%"
                                 role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"
                                 aria-label="ผลลัพธ์ {{ $pct }}%"></div>
                        @endif
                        <span data-target-tick class="absolute -top-1 h-3.5 w-0.5 -ml-px rounded bg-slate-500"
                              style="left: {{ min((float) $ind->target_value, 100) }}%" aria-hidden="true"></span>
                    </div>

                    <div class="text-[.7rem] text-slate-400 tabular-nums flex items-center justify-between gap-2">
                        <span>เป้า {{ $ind->targetLabel() }}</span>
                        <span>{{ number_format($item['numerator']) }} / {{ number_format($item['denominator']) }} {{ $unit['name'] }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endforeach

@endsection
