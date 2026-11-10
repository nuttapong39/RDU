@extends('layouts.admin')

@section('title', 'ผู้ตั้งค่ารหัสยา')

@section('content')
<div class="max-w-3xl">
    <h1 class="text-xl font-bold text-gray-900 mb-1">ผู้ตั้งค่ารหัสยา</h1>
    <p class="text-sm text-gray-600 mb-5">
        ผู้ที่ได้รับสิทธิ์แก้ไขชุดรหัสยาของตัวชี้วัดได้ ผู้ใช้คนอื่นดูได้อย่างเดียว
        ผู้ดูแลระบบมีสิทธิ์นี้อยู่แล้ว เลือกได้เฉพาะผู้ที่เคยเข้าสู่ระบบ
    </p>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <ul class="divide-y divide-gray-100">
            @forelse($users as $user)
                @php($editor = $editors->get($user->id))
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-gray-900 truncate">{{ $user->name }}</div>
                        <div class="text-xs text-gray-500">{{ $user->username }}</div>
                    </div>

                    @if(in_array($user->id, $adminIds, true))
                        <span class="text-xs text-gray-500">ผู้ดูแลระบบ (มีสิทธิ์อยู่แล้ว)</span>
                    @elseif($editor)
                        <form method="POST" action="{{ route('admin.drug-code-editors.destroy', $editor) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="text-sm px-3 py-1.5 rounded-md border border-red-200 text-red-700 hover:bg-red-50 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                ถอนสิทธิ์
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.drug-code-editors.store') }}">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $user->id }}">
                            <button type="submit"
                                    class="text-sm px-3 py-1.5 rounded-md bg-blue-600 text-white hover:bg-blue-700 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                                ให้สิทธิ์
                            </button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="px-4 py-6 text-sm text-gray-500 text-center">ยังไม่มีผู้ใช้ที่เคยเข้าสู่ระบบ</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
