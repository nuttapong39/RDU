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
                'name' => 'ร้อยละการใช้ยาปฏิชีวนะในบาดแผลจากอุบัติเหตุ ไม่นับรวมแผลสัตว์กัด แผลลึกถึงกล้ามเนื้อ และแผลไฟไหม้น้ำร้อนลวก',
                'category' => 'Antibiotic',
                'numerator_desc' => 'จำนวนครั้งบริการบาดแผลสดจากอุบัติเหตุ (ไม่นับรวมแผลสัตว์กัด แผลลึกถึงกล้ามเนื้อ และแผลไฟไหม้น้ำร้อนลวก) ที่ได้รับยาปฏิชีวนะ',
                'denominator_desc' => 'จำนวนครั้งบริการบาดแผลสดจากอุบัติเหตุ (ไม่นับรวมแผลสัตว์กัด แผลลึกถึงกล้ามเนื้อ และแผลไฟไหม้น้ำร้อนลวก) ทั้งหมด',
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
            [
                'code' => 'KPI06',
                'name' => 'ร้อยละการใช้ยาปฏิชีวนะในผู้ป่วยกลุ่ม Pylonephritis, ปัสสาวะอักเสบเฉียบพลัน (ผู้ป่วยนอก)',
                'category' => 'Antibiotic',
                'numerator_desc' => 'มูลค่าการใช้ยาในบัญชียาหลักแห่งชาติ',
                'denominator_desc' => 'มูลค่าการใช้ยาทั้งหมด',
                'target_value' => 80.00,
                'target_direction' => 'higher',
                'sort_order' => 6,
            ],
            [
                'code' => 'KPI07',
                'name' => 'ร้อยละของผู้ป่วยนอกที่มีการใช้ยากลุ่ม NSAIDs ซ้ำซ้อน',
                'category' => 'PatientSafety',
                'numerator_desc' => 'มูลค่าการใช้ยา Generic',
                'denominator_desc' => 'มูลค่าการใช้ยาทั้งหมด',
                'target_value' => 80.00,
                'target_direction' => 'higher',
                'sort_order' => 7,
            ],
            [
                'code' => 'KPI08',
                'name' => 'ร้อยละของผู้ป่วยนอกโรคหืดที่ได้รับยา Inhaled Corticosteroid',
                'category' => 'PatientSafety',
                'numerator_desc' => 'จำนวนผู้ป่วยนอกโรคหืดที่ได้รับยา inhaled corticosteroid',
                'denominator_desc' => 'จำนวนผู้ป่วยนอกโรคหืดทั้งหมด',
                'target_value' => 80.00,
                'target_direction' => 'higher',
                'sort_order' => 8,
            ],
            // ---- KPI09-12 ----
            [
                'code' => 'KPI09',
                'name' => 'ร้อยละของผู้ป่วยนอกโรคไตเรื้อรังระดับ 3 ขึ้นไปที่ได้รับ NSAIDs',
                'category' => 'PatientSafety',
                'numerator_desc' => 'จำนวนผู้ป่วยโรคไตเรื้อรังระดับ 3 ขึ้นไปที่ได้รับยา NSAIDs',
                'denominator_desc' => 'จำนวนผู้ป่วยโรคไตเรื้อรังระดับ 3 ขึ้นไปที่ได้รับยาอย่างน้อย 1 ชนิด',
                'target_value' => 10.00,
                'target_direction' => 'lower',
                'sort_order' => 9,
            ],
            [
                'code' => 'KPI10',
                'name' => 'ร้อยละของผู้ป่วยเบาหวานที่ใช้ยา Metformin เป็นยาชนิดเดียวหรือร่วมกับยาอื่นเพื่อควบคุมระดับน้ำตาลโดยไม่มีข้อห้ามใช้',
                'category' => 'ChronicDisease',
                'numerator_desc' => 'จำนวนผู้ป่วยนอกโรคเบาหวานชนิดที่ 2 (eGFR ≥ 30) ที่ใช้ยา metformin เป็นยาชนิดเดียวหรือร่วมกับยาอื่น',
                'denominator_desc' => 'จำนวนผู้ป่วยนอกโรคเบาหวานชนิดที่ 2 ที่มีผล eGFR ล่าสุดย้อนหลัง 12 เดือน ≥ 30',
                'target_value' => 80.00,
                'target_direction' => 'higher',
                'sort_order' => 10,
            ],
            [
                'code' => 'KPI11',
                'name' => 'ร้อยละผู้รับบริการผู้ป่วยเด็กที่ได้รับการวินิจฉัยเป็นโรคติดเชื้อของทางเดินหายใจและได้รับยาน้ำชนิด non-sedating',
                'category' => 'PatientSafety',
                'numerator_desc' => 'จำนวนผู้ป่วยที่ได้รับ Corticosteroid injection',
                'denominator_desc' => 'จำนวนผู้ป่วย OPD ทั้งหมด',
                'target_value' => 5.00,
                'target_direction' => 'lower',
                'sort_order' => 11,
            ],
            [
                'code' => 'KPI12',
                'name' => 'ร้อยละจำนวนครั้งบริการโรคความดันโลหิตสูงทั่วไปที่ได้รับ RAS Blockage 2 ชนิดร่วมกันในการรักษา',
                'category' => 'PatientSafety',
                'numerator_desc' => 'จำนวนผู้สูงอายุที่ได้รับยา ≥5 รายการ',
                'denominator_desc' => 'จำนวนผู้สูงอายุที่มารับบริการทั้งหมด',
                'target_value' => 20.00,
                'target_direction' => 'lower',
                'sort_order' => 12,
            ],
            // ---- KPI13 ----
            [
                'code' => 'KPI13',
                'name' => 'ร้อยละผู้ป่วยนอกสูงอายุที่ใช้ยากลุ่ม Long-acting Benzodiazepine',
                'category' => 'PatientSafety',
                'numerator_desc' => 'จำนวนครั้งบริการผู้ป่วยนอกสูงอายุที่ได้รับยากลุ่ม Long-acting Benzodiazepine',
                'denominator_desc' => 'จำนวนครั้งบริการผู้ป่วยนอกสูงอายุทั้งหมด',
                'target_value' => 5.00,
                'target_direction' => 'lower',
                'sort_order' => 13,
            ],
        ];

        foreach ($indicators as $data) {
            DB::table('rdu_indicators')->updateOrInsert(
                ['code' => $data['code']],
                array_merge($data, [
                    'is_active' => true,
                    'unit' => '%',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
