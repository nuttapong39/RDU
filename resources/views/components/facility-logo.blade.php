@props(['facility' => null, 'alt' => null])
{{-- The hospital's logo, or a neutral mark until one is uploaded (CONTEXT.md: ข้อมูลสถานบริการ). --}}
@php
    $svc  = app(\App\Services\FacilityProfileService::class);
    $path = $svc->logoPath();
    $name = $facility['name'] ?? '';
@endphp
@if($path)
    <img src="{{ route('facility.logo', ['v' => substr(md5($path), 0, 8)]) }}"
         alt="{{ $alt ?? ($name !== '' ? 'ตราสัญลักษณ์'.$name : 'ตราสัญลักษณ์สถานบริการ') }}" {{ $attributes }}>
@else
    <svg viewBox="0 0 48 48" role="img" aria-label="RDU Dashboard" {{ $attributes }}>
        <circle cx="24" cy="24" r="22" fill="#e0e7ff"/>
        <g transform="rotate(-40 24 24)">
            <rect x="11" y="17" width="26" height="14" rx="7" fill="#fff" stroke="#6366f1" stroke-width="2.4"/>
            <path d="M24 17h6a7 7 0 010 14h-6z" fill="#6366f1"/>
        </g>
    </svg>
@endif
