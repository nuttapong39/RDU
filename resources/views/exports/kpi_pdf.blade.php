<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<style>
    * { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; }
    body { margin: 15px; color: #1f2937; }
    h1 { font-size: 14px; margin: 0 0 4px 0; }
    .sub { font-size: 10px; color: #6b7280; margin-bottom: 12px; }

    .summary-grid { display: table; width: 100%; margin-bottom: 14px; border-collapse: collapse; }
    .summary-row  { display: table-row; }
    .summary-cell { display: table-cell; padding: 4px 8px; border: 1px solid #e5e7eb; }
    .label        { background: #f3f4f6; font-weight: bold; width: 120px; }

    .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 9px; font-weight: bold; }
    .pass    { background: #dcfce7; color: #166534; }
    .warning { background: #fef9c3; color: #854d0e; }
    .fail    { background: #fee2e2; color: #991b1b; }
    .na      { background: #f3f4f6; color: #6b7280; }

    table.detail { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.detail th { background: #1e3a5f; color: white; padding: 5px 6px; text-align: left; font-size: 9px; }
    table.detail td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; font-size: 9px; }
    table.detail tr:nth-child(even) td { background: #f9fafb; }

    .footer { margin-top: 14px; font-size: 8px; color: #9ca3af; text-align: right; }
</style>
</head>
<body>

<h1>{{ $item['indicator']->code }}: {{ $item['indicator']->name }}</h1>
<div class="sub">
    ปีงบประมาณ {{ $year }}
    @if($month > 0) — {{ \App\Services\RduCalculationService::thaiMonths()[$month] ?? '' }} @endif
    &nbsp;|&nbsp; โรงพยาบาลเชียงกลาง จังหวัดน่าน
</div>

{{-- Summary --}}
<table class="summary-grid" style="width:auto; min-width:320px;">
    <tr class="summary-row">
        <td class="summary-cell label">ตัวเศษ</td>
        <td class="summary-cell">{{ number_format($item['numerator']) }}</td>
        <td class="summary-cell label">ตัวส่วน</td>
        <td class="summary-cell">{{ number_format($item['denominator']) }}</td>
    </tr>
    <tr class="summary-row">
        <td class="summary-cell label">ผลลัพธ์</td>
        <td class="summary-cell" style="font-size:13px; font-weight:bold;">
            {{ $item['percentage'] !== null ? $item['percentage'] . '%' : 'N/A' }}
        </td>
        <td class="summary-cell label">เป้าหมาย</td>
        <td class="summary-cell">{{ $item['indicator']->targetLabel() }}</td>
    </tr>
    <tr class="summary-row">
        <td class="summary-cell label">สถานะ</td>
        <td class="summary-cell" colspan="3">
            @php
                $badgeClass = match($item['status']) {
                    'pass' => 'pass', 'warning' => 'warning', 'fail' => 'fail', default => 'na'
                };
                $badgeText = match($item['status']) {
                    'pass' => 'ผ่านเกณฑ์', 'warning' => 'ใกล้เป้าหมาย',
                    'fail' => 'ไม่ผ่านเกณฑ์', default => 'ไม่มีข้อมูล'
                };
            @endphp
            <span class="badge {{ $badgeClass }}">{{ $badgeText }}</span>
        </td>
    </tr>
</table>

{{-- Detail table --}}
@if($details->isNotEmpty())
    <div style="font-weight:bold; margin-bottom:4px;">รายการข้อมูลรายกรณี (แสดงไม่เกิน 500 รายการ)</div>
    <table class="detail">
        <thead>
            <tr>
                @foreach(array_keys($details->first()) as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($details as $row)
                <tr>
                    @foreach($row as $val)
                        <td>{{ $val }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <div style="color:#6b7280; margin-top:12px;">ไม่มีข้อมูลรายกรณี (ใช้งานจริงเมื่อเชื่อมต่อ HOSxP)</div>
@endif

<div class="footer">
    พิมพ์เมื่อ {{ now()->format('d/m/Y H:i') }} | RDU Dashboard — โรงพยาบาลเชียงกลาง
</div>

</body>
</html>
