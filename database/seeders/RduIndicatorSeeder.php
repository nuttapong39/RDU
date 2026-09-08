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
                'name' => 'ร้อยละของผู้ป่วย URI ที่ไม่ได้รับยาปฏิชีวนะ',
                'category' => 'Antibiotic',
                'numerator_desc' => 'จำนวนผู้ป่วย URI ที่ไม่ได้รับยาปฏิชีวนะ',
                'denominator_desc' => 'จำนวนผู้ป่วย URI ทั้งหมด',
                'target_value' => 80.00,
                'target_direction' => 'higher',
                'sort_order' => 1,
            ],
            [
                'code' => 'KPI02',
                'name' => 'ร้อยละของผู้ป่วย Diarrhea ที่ไม่ได้รับยาปฏิชีวนะ',
                'category' => 'Antibiotic',
                'numerator_desc' => 'จำนวนผู้ป่วย Diarrhea ที่ไม่ได้รับยาปฏิชีวนะ',
                'denominator_desc' => 'จำนวนผู้ป่วย Diarrhea ทั้งหมด',
                'target_value' => 80.00,
                'target_direction' => 'higher',
                'sort_order' => 2,
            ],
            [
                'code' => 'KPI03',
                'name' => 'ร้อยละของผู้ป่วย Wound ที่ไม่ได้รับยาปฏิชีวนะ',
                'category' => 'Antibiotic',
                'numerator_desc' => 'จำนวนผู้ป่วย Wound ที่ไม่ได้รับยาปฏิชีวนะ',
                'denominator_desc' => 'จำนวนผู้ป่วย Wound ทั้งหมด',
                'target_value' => 80.00,
                'target_direction' => 'higher',
                'sort_order' => 3,
            ],
            [
                'code' => 'KPI04',
                'name' => 'ร้อยละการใช้ยาปฏิชีวนะใน IPD',
                'category' => 'Antibiotic',
                'numerator_desc' => 'จำนวนผู้ป่วย IPD ที่ได้รับยาปฏิชีวนะ',
                'denominator_desc' => 'จำนวนผู้ป่วย IPD ทั้งหมด',
                'target_value' => 40.00,
                'target_direction' => 'lower',
                'sort_order' => 4,
            ],
            [
                'code' => 'KPI05',
                'name' => 'ร้อยละความเหมาะสมของการใช้ยาปฏิชีวนะใน CAP',
                'category' => 'Antibiotic',
                'numerator_desc' => 'จำนวนผู้ป่วย CAP ที่ได้รับยาปฏิชีวนะอย่างเหมาะสม',
                'denominator_desc' => 'จำนวนผู้ป่วย CAP ทั้งหมด',
                'target_value' => 80.00,
                'target_direction' => 'higher',
                'sort_order' => 5,
            ],
            // ---- Drug Quality ----
            [
                'code' => 'KPI06',
                'name' => 'ร้อยละมูลค่าการใช้ยาในบัญชียาหลักแห่งชาติ',
                'category' => 'DrugQuality',
                'numerator_desc' => 'มูลค่าการใช้ยาในบัญชียาหลักแห่งชาติ',
                'denominator_desc' => 'มูลค่าการใช้ยาทั้งหมด',
                'target_value' => 80.00,
                'target_direction' => 'higher',
                'sort_order' => 6,
            ],
            [
                'code' => 'KPI07',
                'name' => 'ร้อยละมูลค่าการใช้ยา Generic',
                'category' => 'DrugQuality',
                'numerator_desc' => 'มูลค่าการใช้ยา Generic',
                'denominator_desc' => 'มูลค่าการใช้ยาทั้งหมด',
                'target_value' => 80.00,
                'target_direction' => 'higher',
                'sort_order' => 7,
            ],
            [
                'code' => 'KPI08',
                'name' => 'ร้อยละมูลค่าการใช้ยาต้านจุลชีพ',
                'category' => 'DrugQuality',
                'numerator_desc' => 'มูลค่าการใช้ยาต้านจุลชีพ',
                'denominator_desc' => 'มูลค่าการใช้ยาทั้งหมด',
                'target_value' => 20.00,
                'target_direction' => 'lower',
                'sort_order' => 8,
            ],
            // ---- Patient Safety ----
            [
                'code' => 'KPI09',
                'name' => 'ร้อยละผู้สูงอายุที่ได้รับยา NSAIDs',
                'category' => 'PatientSafety',
                'numerator_desc' => 'จำนวนผู้สูงอายุ (≥60 ปี) ที่ได้รับยา NSAIDs',
                'denominator_desc' => 'จำนวนผู้สูงอายุที่มารับบริการทั้งหมด',
                'target_value' => 20.00,
                'target_direction' => 'lower',
                'sort_order' => 9,
            ],
            [
                'code' => 'KPI10',
                'name' => 'ร้อยละผู้ป่วยที่ได้รับ Vitamin B1 injection',
                'category' => 'PatientSafety',
                'numerator_desc' => 'จำนวนผู้ป่วยที่ได้รับ Vitamin B1 injection',
                'denominator_desc' => 'จำนวนผู้ป่วย OPD ทั้งหมด',
                'target_value' => 5.00,
                'target_direction' => 'lower',
                'sort_order' => 10,
            ],
            [
                'code' => 'KPI11',
                'name' => 'ร้อยละผู้ป่วยที่ได้รับ Corticosteroid injection',
                'category' => 'PatientSafety',
                'numerator_desc' => 'จำนวนผู้ป่วยที่ได้รับ Corticosteroid injection',
                'denominator_desc' => 'จำนวนผู้ป่วย OPD ทั้งหมด',
                'target_value' => 5.00,
                'target_direction' => 'lower',
                'sort_order' => 11,
            ],
            [
                'code' => 'KPI12',
                'name' => 'ร้อยละผู้สูงอายุที่ได้รับยาหลายชนิด (Polypharmacy)',
                'category' => 'PatientSafety',
                'numerator_desc' => 'จำนวนผู้สูงอายุที่ได้รับยา ≥5 รายการ',
                'denominator_desc' => 'จำนวนผู้สูงอายุที่มารับบริการทั้งหมด',
                'target_value' => 20.00,
                'target_direction' => 'lower',
                'sort_order' => 12,
            ],
            // ---- Chronic Disease ----
            [
                'code' => 'KPI13',
                'name' => 'ร้อยละผู้ป่วยเบาหวานที่ควบคุม HbA1c ได้',
                'category' => 'ChronicDisease',
                'numerator_desc' => 'จำนวนผู้ป่วยเบาหวานที่มี HbA1c < 7%',
                'denominator_desc' => 'จำนวนผู้ป่วยเบาหวานที่ได้รับการตรวจ HbA1c',
                'target_value' => 40.00,
                'target_direction' => 'higher',
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
