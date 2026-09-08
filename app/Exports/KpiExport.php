<?php

namespace App\Exports;

use App\Services\RduCalculationService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KpiExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        private array  $summary,
        private array  $details,
        private string $indicatorCode,
        private string $indicatorName,
        private int    $year,
        private int    $month,
    ) {}

    public function title(): string
    {
        return $this->indicatorCode;
    }

    public function headings(): array
    {
        return [];
    }

    public function array(): array
    {
        $monthLabel = $this->month === 0 ? 'ทั้งปี' : (RduCalculationService::thaiMonths()[$this->month] ?? $this->month);

        $rows = [
            // Header block
            ['ตัวชี้วัด', $this->indicatorCode . ' — ' . $this->indicatorName],
            ['ปีงบประมาณ', $this->year],
            ['เดือน', $monthLabel],
            ['ตัวเศษ', $this->summary['numerator']],
            ['ตัวส่วน', $this->summary['denominator']],
            ['ผลลัพธ์ (%)', $this->summary['percentage'] !== null ? $this->summary['percentage'] . '%' : 'N/A'],
            ['เป้าหมาย', $this->summary['target']],
            ['สถานะ', match ($this->summary['status']) {
                'pass'    => 'ผ่านเกณฑ์',
                'warning' => 'ใกล้เป้าหมาย',
                'fail'    => 'ไม่ผ่านเกณฑ์',
                default   => 'ไม่มีข้อมูล',
            }],
            [], // blank separator
        ];

        if (!empty($this->details)) {
            // Column headers from first row keys
            $rows[] = array_keys($this->details[0]);
            foreach ($this->details as $row) {
                $rows[] = array_values($row);
            }
        } else {
            $rows[] = ['ไม่มีข้อมูลรายกรณี'];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            'A' => ['font' => ['bold' => true]],
        ];
    }
}
