@extends('layouts.dashboard')

@section('title', 'ตั้งค่ารหัสยา')

@section('content')

<div class="mb-5">
    <a href="{{ route('dashboard') }}"
       class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 mb-3 cursor-pointer transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded">
        <span class="msi text-[1rem]" aria-hidden="true">arrow_back</span>
        กลับภาพรวม
    </a>
    <h1 class="text-xl font-bold text-slate-800 leading-tight">ตั้งค่ารหัสยา</h1>
    <p class="text-slate-500 text-sm mt-1">
        รหัสยา (icode) ที่แต่ละตัวชี้วัดใช้นับ แต่ละโรงพยาบาลมี icode ต่างกัน ตัวชี้วัดที่ยังไม่ตั้งค่าจะยังไม่แสดงผล
        ชุดรหัสยาที่ใช้ร่วมกันหลายตัวชี้วัด การแก้ไขชุดนั้นจะเปลี่ยนผลของทุกตัวที่ใช้
    </p>
    <p class="mt-2 text-xs text-slate-500" role="note">
        @can('edit-drug-code-sets')
            คุณมีสิทธิ์แก้ไขชุดรหัสยา — ฟอร์มแก้ไขจะเปิดให้ใช้ในเวอร์ชันถัดไป หน้านี้แสดงสถานะปัจจุบัน
        @else
            คุณดูชุดรหัสยาได้อย่างเดียว การแก้ไขต้องได้รับสิทธิ์ "ผู้ตั้งค่ารหัสยา" จากผู้ดูแลระบบ
        @endcan
    </p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    @foreach($kpis as $row)
        @php $ind = $row['indicator']; @endphp
        <article class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5" data-drug-code-kpi="{{ $ind->code }}">
            <div class="flex items-center gap-2 mb-2">
                <span class="cat-badge text-sm px-2.5 py-0.5">{{ $ind->code }}</span>
                @if($row['configured'])
                    <span class="ml-auto text-xs font-semibold text-emerald-700">ตั้งค่าแล้ว</span>
                @else
                    <span class="ml-auto text-xs font-semibold text-slate-500">ยังไม่ตั้งค่า</span>
                @endif
            </div>
            <h2 class="text-sm font-semibold text-slate-800 leading-snug mb-3">{{ $ind->name }}</h2>
            <ul class="space-y-1.5 text-sm">
                @foreach($row['sets'] as $set)
                    <li class="flex flex-wrap items-baseline gap-x-2">
                        <span class="text-slate-700">{{ $set['label'] }}</span>
                        @if($set['count'] > 0)
                            <span class="text-xs text-slate-500 tabular-nums">{{ number_format($set['count']) }} รายการ</span>
                        @else
                            <span class="text-xs text-slate-500">ยังไม่มีรายการ</span>
                        @endif
                        @if($set['sharedWith'])
                            <span class="text-xs text-slate-400">ใช้ร่วมกับ {{ implode(', ', $set['sharedWith']) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </article>
    @endforeach
</div>

@endsection
