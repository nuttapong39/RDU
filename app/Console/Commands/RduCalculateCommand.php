<?php

namespace App\Console\Commands;

use App\Models\RduIndicator;
use App\Services\RduCalculationService;
use Illuminate\Console\Command;

class RduCalculateCommand extends Command
{
    protected $signature = 'rdu:calculate
                            {year : ปีงบประมาณ (พ.ศ.) เช่น 2569}
                            {month : เดือน (1-12) หรือ 0 สำหรับทุกเดือนในปีนั้น}';

    protected $description = 'คำนวณ KPI ทั้ง 13 ตัว และบันทึกผลลัพธ์ลงฐานข้อมูล';

    public function handle(RduCalculationService $calcService): int
    {
        $year  = (int) $this->argument('year');
        $month = (int) $this->argument('month');

        if ($year < 2560 || $year > 2600) {
            $this->error("ปีงบประมาณไม่ถูกต้อง: {$year} (ต้องอยู่ระหว่าง 2560-2600)");
            return self::FAILURE;
        }

        if ($month !== 0 && ($month < 1 || $month > 12)) {
            $this->error("เดือนไม่ถูกต้อง: {$month} (ต้องเป็น 1-12 หรือ 0 สำหรับทุกเดือน)");
            return self::FAILURE;
        }

        $months = $month === 0 ? range(1, 12) : [$month];

        foreach ($months as $m) {
            $label = self::thaiMonthLabel($m, $year);
            $this->info("คำนวณ {$label} ...");

            $results = $calcService->calculateAll($year, $m);

            $rows = $results->map(function ($r) {
                $pct    = $r['percentage'] !== null ? $r['percentage'] . '%' : 'N/A';
                $status = match ($r['status']) {
                    'pass'    => '<fg=green>ผ่าน</>',
                    'warning' => '<fg=yellow>ใกล้เป้า</>',
                    'fail'    => '<fg=red>ไม่ผ่าน</>',
                    default   => '<fg=gray>N/A</>',
                };
                return [
                    $r['indicator']->code,
                    mb_substr($r['indicator']->name, 0, 30),
                    $r['numerator'],
                    $r['denominator'],
                    $pct,
                    $r['indicator']->targetLabel(),
                    $status,
                ];
            })->values()->all();

            $this->table(
                ['Code', 'ชื่อตัวชี้วัด', 'ตัวเศษ', 'ตัวส่วน', 'ผล%', 'เป้าหมาย', 'สถานะ'],
                $rows
            );
        }

        $this->newLine();
        $this->info('✅ บันทึกผลลัพธ์ลงฐานข้อมูลเรียบร้อยแล้ว');
        return self::SUCCESS;
    }

    private static function thaiMonthLabel(int $month, int $fyYear): string
    {
        $names = RduCalculationService::thaiMonths();
        // Months 10-12 belong to previous BE year
        $year = $month >= 10 ? $fyYear - 1 : $fyYear;
        return ($names[$month] ?? "เดือน {$month}") . " {$year}";
    }
}
