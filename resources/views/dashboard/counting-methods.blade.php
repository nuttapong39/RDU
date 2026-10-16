@extends('layouts.dashboard')

@section('title', 'วิธีนับทุกตัวชี้วัด')

@section('content')

<div class="mb-5">
    <a href="{{ route('dashboard') }}"
       class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 mb-3 cursor-pointer transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded">
        <span class="msi text-[1rem]" aria-hidden="true">arrow_back</span>
        กลับภาพรวม
    </a>
    <h1 class="text-xl font-bold text-slate-800 leading-tight">วิธีนับทุกตัวชี้วัด</h1>
    <p class="text-slate-500 text-sm mt-1">
        นับจากอะไร ใช้เกณฑ์ใด และใช้รหัสยา/รหัสโรคใดบ้าง — รหัสทั้งหมดดึงจากเงื่อนไขที่ระบบใช้นับจริง
        ใช้ทบทวนร่วมกับเภสัชกรก่อนปรับแก้นิยาม
    </p>
</div>

{{-- Jump list --}}
<nav aria-label="ตัวชี้วัด" class="mb-6 flex flex-wrap gap-2">
    @foreach($entries as $entry)
        <a href="#method-{{ $entry['indicator']->code }}"
           class="px-2.5 py-1 rounded-lg border text-xs font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500
                  {{ $entry['cm']['method'] ? 'border-indigo-200 text-indigo-700 bg-white hover:bg-indigo-50' : 'border-amber-200 text-amber-700 bg-amber-50 hover:bg-amber-100' }}">
            <span class="font-mono">{{ $entry['indicator']->code }}</span>
            @unless($entry['cm']['method'])<span class="font-normal">· รอนิยาม</span>@endunless
        </a>
    @endforeach
</nav>

<div class="space-y-6">
    @foreach($entries as $entry)
        @php $ind = $entry['indicator']; @endphp
        <article id="method-{{ $ind->code }}" class="scroll-mt-4 bg-white rounded-2xl border border-slate-200 shadow-sm p-6"
                 aria-labelledby="method-title-{{ $ind->code }}">
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <span class="cat-badge text-sm px-2.5 py-0.5">{{ $ind->code }}</span>
                <h2 id="method-title-{{ $ind->code }}" class="font-bold text-slate-800 flex-1 min-w-0">{{ $ind->name }}</h2>
                <a href="{{ route('dashboard.kpi', $ind->code) }}"
                   class="text-xs text-indigo-600 hover:text-indigo-800 underline underline-offset-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded">
                    เปิดหน้าตัวชี้วัด
                </a>
            </div>
            @include('dashboard.partials.counting-method', ['ind' => $ind, 'cm' => $entry['cm']])
        </article>
    @endforeach
</div>

@endsection
