@php
    $fontSvc     = app(\App\Services\FontService::class);
    $fontCurrent = $fontSvc->current();
    $fontUrl     = $fontSvc->isCustomized()
        ? $fontSvc->stylesheetUrl([$fontCurrent['sans'], $fontCurrent['mono']])
        : $fontSvc->defaultStylesheetUrl();
    $fontSansStack = $fontSvc->sansStack($fontCurrent['sans']);
    $fontMonoStack = $fontSvc->monoStack($fontCurrent['mono']);
@endphp
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
@if($fontUrl)
    <link href="{{ $fontUrl }}" rel="stylesheet">
@endif
@php $fontScale = $fontSvc->scale(); @endphp
<style>:root{--font-sans:{!! $fontSansStack !!};--font-mono:{!! $fontMonoStack !!};}@if($fontScale !== \App\Services\FontService::DEFAULT_SCALE) html{font-size:{{ $fontScale }}%;}@endif</style>
<script>window.RDU_FONT_SANS = @json($fontSansStack); window.RDU_FONT_MONO = @json($fontMonoStack); window.RDU_FONT_SCALE = {{ (float) $fontScale / 100 }};</script>
