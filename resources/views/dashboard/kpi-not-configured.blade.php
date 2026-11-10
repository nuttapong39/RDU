@extends('layouts.dashboard')

@section('title', $indicator->code . ': ' . $indicator->name)

@section('content')

<div class="mb-5">
    <a href="{{ route('dashboard') }}"
       class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 mb-3 cursor-pointer transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded">
        <span class="msi text-[1rem]" aria-hidden="true">arrow_back</span>
        กลับภาพรวม
    </a>
    <div class="flex flex-wrap items-center gap-3">
        <span class="cat-badge text-sm px-2.5 py-0.5">{{ $indicator->code }}</span>
        <h1 class="text-xl font-bold text-slate-800 leading-tight flex-1 min-w-0">{{ $indicator->name }}</h1>
    </div>
    <p class="text-slate-500 text-sm mt-1">เป้าหมาย {{ $indicator->targetLabel() }}</p>
</div>

<section data-not-configured-card class="max-w-xl bg-white rounded-2xl border border-slate-200 shadow-sm p-6 text-center"
         aria-labelledby="nc-title">
    <span class="msi text-4xl text-slate-400" aria-hidden="true">medication</span>
    <h2 id="nc-title" class="mt-2 text-lg font-bold text-slate-800">ยังไม่ได้ตั้งค่าตัวชี้วัดนี้</h2>
    <p class="mt-1 text-sm text-slate-500">
        ต้องกำหนดรหัสยา (icode) ของโรงพยาบาลก่อน ระบบจึงนับและแสดงผลตัวชี้วัดนี้ได้
    </p>

    @if($missingSets)
        <ul class="mt-3 text-sm text-slate-600 list-disc inline-block text-left pl-5" aria-label="ชุดรหัสยาที่ยังขาด">
            @foreach($missingSets as $key)
                <li>ยังขาด: {{ \App\Support\DrugCodeSetRegistry::get($key)['label'] }}</li>
            @endforeach
        </ul>
    @endif

    <div class="mt-5">
        <a href="{{ route('drug-code-sets.index') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium cursor-pointer transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
            <span class="msi text-[1.1rem]" aria-hidden="true">settings</span>
            ไปหน้าตั้งค่ารหัสยา
        </a>
    </div>
</section>

@endsection
