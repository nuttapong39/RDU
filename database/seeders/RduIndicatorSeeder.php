<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RduIndicatorSeeder extends Seeder
{
    public function run(): void
    {
        $indicators = [
            // ---- Antibiotic ----
            [
                'code' => 'KPI01',
                'name' => 'ร้อยละการใช้ยาปฏิชีวนะในบาดแผลจากอุบัติเหตุ ครอบคลุมแผลทุกชนิด',
                'category' => 'Antibiotic',
                'numerator_desc' => 'จำนวนครั้งบริการแผลอุบัติเหตุที่ได้รับยาปฏิชีวนะ',
                'denominator_desc' => 'จำนวนครั้งบริการแผลอุบัติเหตุทั้งหมด',
                'target_value' => 40.00,
                'target_direction' => 'lower',
                'sort_order' => 1,
            ],
            [
                'code' => 'KPI02',
                'name' => 'ร้อยละการใช้ยาปฏิชีวนะในบาดแผลสดจากอุบัติเหตุ ไม่นับรวมแผลสัตว์กัด แผลลึกถึงกล้ามเนื้อ และแผลไฟไหม้น้ำร้อนลวก',
                'category' => 'Antibiotic',
                'numerator_desc' => 'จำนวนครั้งบริการ (visit) ในตัวส่วนที่ได้รับยาปฏิชีวนะชนิดกินหรือฉีด',
                'denominator_desc' => 'จำนวนครั้งบริการ (visit) ผู้ป่วยนอกที่มีทั้งรหัสบาดแผลและรหัสสาเหตุจากอุบัติเหตุ (V/W/X00–X59) ไม่นับรวมแผลสัตว์กัด (W54–W56) แผลลึกถึงกล้ามเนื้อ/เอ็น และแผลไฟไหม้น้ำร้อนลวก (T20–T32)',
                'target_value' => 40.00,
                'target_direction' => 'lower',
                'sort_order' => 2,
            ],
            [
                'code' => 'KPI03',
                'name' => 'ร้อยละการใช้ยาปฏิชีวนะในหญิงคลอดปกติครบกำหนด',
                'category' => 'Antibiotic',
                'numerator_desc' => 'จำนวนผู้ป่วยหญิงคลอดปกติครบกำหนดที่ไม่ได้รับยาปฏิชีวนะ',
                'denominator_desc' => 'จำนวนผู้ป่วยหญิงคลอดปกติครบกำหนดทั้งหมด',
                'target_value' => 80.00,
                'target_direction' => 'higher',
                'sort_order' => 3,
            ],
            [
                'code' => 'KPI04',
                'name' => 'ร้อยละการใช้ยาปฏิชีวนะในโรคติดเชื้อที่ระบบการหายใจช่วงบนและหลอดลมอักเสบเฉียบพลันในผู้ป่วยนอก',
                'category' => 'Antibiotic',
                'numerator_desc' => 'จำนวนครั้งบริการโรคติดเชื้อทางเดินหายใจส่วนบน/หลอดลมอักเสบเฉียบพลันที่ได้รับยาปฏิชีวนะ',
                'denominator_desc' => 'จำนวนครั้งบริการโรคติดเชื้อทางเดินหายใจส่วนบน/หลอดลมอักเสบเฉียบพลันทั้งหมด',
                'target_value' => 40.00,
                'target_direction' => 'lower',
                'sort_order' => 4,
            ],
            [
                'code' => 'KPI05',
                'name' => 'ร้อยละการใช้ยาปฏิชีวนะในโรคอุจจาระร่วงเฉียบพลัน',
                'category' => 'Antibiotic',
                'numerator_desc' => 'จำนวนครั้งบริการโรคอุจจาระร่วงเฉียบพลันที่ได้รับยาปฏิชีวนะ',
                'denominator_desc' => 'จำนวนครั้งบริการโรคอุจจาระร่วงเฉียบพลันทั้งหมด',
                'target_value' => 20.00,
                'target_direction' => 'lower',
                'sort_order' => 5,
            ],
            // ---- KPI06-08 ----
            // KPI06 is no longer tracked (removed 2026-10-17: no data source). The row stays, switched off,
            // so its stored results are kept and the other KPIs keep their codes.
            [
                'code' => 'KPI06',
                'name' => 'ร้อยละการใช้ยาปฏิชีวนะในผู้ป่วยกลุ่ม Pylonephritis, ปัสสาวะอักเสบเฉียบพลัน (ผู้ป่วยนอก)',
                'category' => 'Antibiotic',
                'numerator_desc' => 'มูลค่าการใช้ยาในบัญชียาหลักแห่งชาติ',
                'denominator_desc' => 'มูลค่าการใช้ยาทั้งหมด',
                'target_value' => 80.00,
                'target_direction' => 'higher',
                'sort_order' => 6,
                'is_active' => false,
            ],
            [
                'code' => 'KPI07',
                'name' => 'ร้อยละของผู้ป่วยนอกที่มีการใช้ยากลุ่ม NSAIDs ซ้ำซ้อน',
                'category' => 'PatientSafety',
                'numerator_desc' => 'จำนวนครั้งบริการ (visit) ผู้ป่วยนอกที่ได้รับยากลุ่ม NSAIDs ตั้งแต่ 2 รายการในครั้งเดียวกัน',
                'denominator_desc' => 'จำนวนครั้งบริการ (visit) ผู้ป่วยนอกที่ได้รับยากลุ่ม NSAIDs อย่างน้อย 1 รายการ',
                'target_value' => 5.00,
                'target_direction' => 'lower',
                'sort_order' => 7,
            ],
            [
                'code' => 'KPI08',
                'name' => 'ร้อยละของผู้ป่วยนอกโรคหืดที่ได้รับยา Inhaled Corticosteroid',
                'category' => 'PatientSafety',
                'numerator_desc' => 'จำนวนผู้ป่วย (คน) ในตัวหารที่ได้รับยา Inhaled Corticosteroid ในการมารับบริการผู้ป่วยนอกครั้งใดก็ได้ในช่วงเวลาเดียวกัน',
                'denominator_desc' => 'จำนวนผู้ป่วย (คน) ที่มารับบริการผู้ป่วยนอกและได้รับการวินิจฉัยโรคหืด (ICD-10 J45, J46) ในช่วงเวลานั้น',
                'target_value' => 80.00,
                'target_direction' => 'higher',
                'sort_order' => 8,
            ],
            // ---- KPI09-12 ----
            [
                'code' => 'KPI09',
                'name' => 'ร้อยละของผู้ป่วยนอกโรคไตเรื้อรังระดับ 3 ขึ้นไปที่ได้รับ NSAIDs',
                'category' => 'PatientSafety',
                'numerator_desc' => 'จำนวนผู้ป่วย (คน) ในตัวหารที่ได้รับยา NSAIDs ในการมารับบริการผู้ป่วยนอกครั้งใดก็ได้ในช่วงเวลาเดียวกัน',
                'denominator_desc' => 'จำนวนผู้ป่วย (คน) ที่มารับบริการผู้ป่วยนอกและได้รับการวินิจฉัยโรคไตเรื้อรังระดับ 3 ขึ้นไป (ICD-10 N183, N184, N185) ในช่วงเวลานั้น',
                'target_value' => 10.00,
                'target_direction' => 'lower',
                'sort_order' => 9,
            ],
            [
                'code' => 'KPI10',
                'name' => 'ร้อยละของผู้ป่วยเบาหวานที่ใช้ยา Metformin เป็นยาชนิดเดียวหรือร่วมกับยาอื่นเพื่อควบคุมระดับน้ำตาลโดยไม่มีข้อห้ามใช้',
                'category' => 'ChronicDisease',
                'numerator_desc' => 'จำนวนผู้ป่วยในตัวส่วนที่ได้รับยา Metformin (เป็นยาชนิดเดียวหรือร่วมกับยาอื่น) ในวิสิตผู้ป่วยนอกครั้งใดก็ได้ในช่วงเวลาเดียวกัน',
                'denominator_desc' => 'จำนวนผู้ป่วยนอกโรคเบาหวานชนิดที่ 2 (ICD-10 E11) ที่ได้รับยาลดระดับน้ำตาลอย่างน้อย 1 ชนิด และมีผล eGFR ล่าสุดย้อนหลัง 12 เดือน ≥ 30',
                'target_value' => 80.00,
                'target_direction' => 'higher',
                'sort_order' => 10,
            ],
            [
                'code' => 'KPI11',
                'name' => 'ร้อยละผู้รับบริการผู้ป่วยเด็กที่ได้รับการวินิจฉัยเป็นโรคติดเชื้อของทางเดินหายใจและได้รับยาน้ำชนิด non-sedating',
                'category' => 'PatientSafety',
                'numerator_desc' => 'จำนวนครั้งบริการ (visit) ในตัวส่วนที่ได้รับยาแก้แพ้ชนิด non-sedating (Cetirizine ยาน้ำหรือยาเม็ด)',
                'denominator_desc' => 'จำนวนครั้งบริการ (visit) ผู้ป่วยนอกเด็กอายุต่ำกว่า 15 ปี ที่ได้รับการวินิจฉัยโรคติดเชื้อทางเดินหายใจ (ICD-10 J00–J09, J20, J21, J22)',
                'target_value' => 20.00,
                'target_direction' => 'lower',
                'sort_order' => 11,
            ],
            [
                'code' => 'KPI12',
                'name' => 'ร้อยละจำนวนครั้งบริการโรคความดันโลหิตสูงทั่วไปที่ได้รับ RAS Blockade 2 ชนิดร่วมกันในการรักษา',
                'category' => 'PatientSafety',
                'numerator_desc' => 'จำนวนครั้งบริการ (visit) ผู้ป่วยนอกโรคความดันโลหิตสูงทั่วไป (I10) ที่ได้รับยากลุ่ม RAS blockade (ACEI / ARB) ตั้งแต่ 2 ตัวยาที่ต่างกันในครั้งเดียวกัน',
                'denominator_desc' => 'จำนวนครั้งบริการ (visit) ผู้ป่วยนอกโรคความดันโลหิตสูงทั่วไป (I10) ที่ได้รับยากลุ่ม RAS blockade อย่างน้อย 1 ชนิด',
                'target_value' => 0.00,
                'target_direction' => 'lower',
                'sort_order' => 12,
            ],
            // ---- KPI13 ----
            [
                'code' => 'KPI13',
                'name' => 'ร้อยละผู้ป่วยนอกสูงอายุที่ใช้ยากลุ่ม Long-acting Benzodiazepine',
                'category' => 'PatientSafety',
                'numerator_desc' => 'จำนวนครั้งบริการ (visit) ในตัวส่วนที่ได้รับยากลุ่ม Long-acting Benzodiazepine (Diazepam ยาเม็ดหรือยาฉีด)',
                'denominator_desc' => 'จำนวนครั้งบริการ (visit) ทั้งหมดของผู้สูงอายุ 65 ปีขึ้นไป ตามวันที่มารับบริการ (รวมวิสิตที่ต่อมารับไว้นอนโรงพยาบาล ตาม HDC)',
                'target_value' => 5.00,
                'target_direction' => 'lower',
                'sort_order' => 13,
            ],
        ];

        foreach ($indicators as $data) {
            DB::table('rdu_indicators')->updateOrInsert(
                ['code' => $data['code']],
                array_merge(['is_active' => true], $data, [
                    'unit' => '%',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
