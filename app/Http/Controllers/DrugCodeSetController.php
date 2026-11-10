<?php

namespace App\Http\Controllers;

use App\Exceptions\DrugCodeSetConflictException;
use App\Models\AuditLog;
use App\Models\RduIndicator;
use App\Services\DrugCodeSetService;
use App\Services\RduQueryService;
use App\Support\DrugCodeSetRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** The hospital's drug code sets (CONTEXT.md: ชุดรหัสยา) — every signed-in user may look; editing is for the ผู้ตั้งค่ารหัสยา. */
class DrugCodeSetController extends Controller
{
    /** A drug code or lab item code: plain characters only, because codes end up inside SQL lists. */
    private const CODE_PATTERN = '/^[A-Za-z0-9._-]{1,30}$/';

    public function __construct(private DrugCodeSetService $sets, private RduQueryService $query) {}

    public function index(): View
    {
        $canEdit = DrugCodeSetService::userCanEdit(auth()->user());

        // Editors also see the KPIs switched off, to switch them back on; everyone else sees the ones in use.
        $indicators = RduIndicator::when(! $canEdit, fn ($q) => $q->active())->ordered()->get()
            ->filter(fn (RduIndicator $i) => DrugCodeSetRegistry::forKpi($i->code) !== []);

        $kpis = $indicators->map(fn (RduIndicator $i) => [
            'indicator'  => $i,
            'configured' => $this->sets->isKpiConfigured($i->code),
            'sets'       => array_map(fn (string $key) => [
                'key'        => $key,
                'label'      => DrugCodeSetRegistry::get($key)['label'],
                'count'      => count($this->sets->items($key)),
                'sharedWith' => array_values(array_diff(DrugCodeSetRegistry::get($key)['kpis'], [$i->code])),
            ], DrugCodeSetRegistry::forKpi($i->code)),
        ]);

        return view('dashboard.drug-code-sets', [
            'kpis'    => $kpis,
            'canEdit' => $canEdit,
            'editor'  => $canEdit ? $this->editorConfig($kpis) : null,
        ]);
    }

    /** The hospital's drug list (`drugitems` columns only) for the left-hand side of the editor. */
    public function catalog(): JsonResponse
    {
        return response()->json(['drugs' => $this->query->drugCatalog()]);
    }

    /** Lab items matching a search, for choosing the eGFR lab item. */
    public function labItems(Request $request): JsonResponse
    {
        return response()->json(['items' => $this->query->searchLabItems((string) $request->query('q', ''))]);
    }

    /**
     * Switch a KPI on or off for this hospital (rdu_indicators.is_active). Off = gone from the sidebar, overview,
     * counting methods, login snapshot and `rdu:calculate`; its drug code sets and stored results are kept.
     * Only KPIs that have a HOSxP query can be switched.
     */
    public function setActive(Request $request, string $code): JsonResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);

        $indicator = RduIndicator::where('code', strtoupper($code))->firstOrFail();
        abort_unless($indicator->hasHosxpQuery(), 404);

        $was = $indicator->is_active;
        $indicator->update(['is_active' => (bool) $data['active']]);

        if ($was !== $indicator->is_active) {
            AuditLog::record('kpi.active', [
                'subject'   => "kpi:{$indicator->code}",
                'old_value' => ['is_active' => $was],
                'new_value' => ['is_active' => $indicator->is_active],
            ]);
        }

        return response()->json([
            'active'  => $indicator->is_active,
            'message' => ($indicator->is_active ? 'เปิดใช้งาน ' : 'ปิดใช้งาน ').$indicator->code.' แล้ว',
        ]);
    }

    /** Save one set. 409 when someone else saved it first, 422 when the content is not valid. */
    public function update(Request $request, string $set): JsonResponse
    {
        abort_unless(DrugCodeSetRegistry::exists($set), 404);
        $def = DrugCodeSetRegistry::get($set);

        $data = $request->validate([
            'version'           => ['required', 'integer', 'min:0'],
            'confirm_empty'     => ['sometimes', 'boolean'],
            'items'             => ['present', 'array', 'max:'.($def['max'] ?? 1000)],
            'items.*.code'      => ['required', 'string', 'regex:'.self::CODE_PATTERN, 'distinct'],
            'items.*.attribute' => [$def['attribute'] ? 'required' : 'nullable', 'string', 'max:60'],
        ], [
            'items.max'                  => 'ชุดนี้เลือกได้ไม่เกิน '.($def['max'] ?? 0).' รายการ',
            'items.*.code.regex'         => 'รหัสมีอักขระที่ไม่อนุญาต',
            'items.*.code.distinct'      => 'มีรหัสซ้ำในชุดเดียวกัน',
            'items.*.attribute.required' => 'ต้องระบุ'.($def['attribute'] === DrugCodeSetRegistry::ATTR_FORM ? 'รูปแบบยา' : 'ตัวยา').'ให้ครบทุกรายการ',
        ]);

        $items = array_map(fn (array $i) => [
            'code'      => (string) $i['code'],
            'attribute' => $def['attribute'] ? trim((string) $i['attribute']) : null,
        ], $data['items']);

        if ($items === [] && ! ($data['confirm_empty'] ?? false)) {
            return response()->json([
                'message'            => 'ชุดว่างจะทำให้ตัวชี้วัดที่ใช้ชุดนี้กลับเป็น “ยังไม่ตั้งค่า” — ต้องยืนยันก่อนบันทึก',
                'needs_confirmation' => true,
            ], 422);
        }

        if ($unknown = $this->unknownCodes($def['kind'], array_column($items, 'code'))) {
            return response()->json([
                'message' => 'ไม่พบรหัสนี้ในฐานข้อมูลโรงพยาบาล: '.implode(', ', array_slice($unknown, 0, 5)),
                'errors'  => ['items' => ['ไม่พบรหัสในฐานข้อมูลโรงพยาบาล']],
            ], 422);
        }

        $oldCodes = $this->sets->codes($set);

        try {
            $this->sets->save($set, $items, (int) $data['version'], $request->user()->id);
        } catch (DrugCodeSetConflictException) {
            return response()->json([
                'message'  => 'มีผู้อื่นแก้ไขชุดนี้แล้ว กรุณาโหลดหน้าใหม่เพื่อดูค่าล่าสุดก่อนแก้ไขต่อ',
                'conflict' => true,
            ], 409);
        }

        $newCodes = array_column($items, 'code');
        AuditLog::record('drug_code_set.update', [
            'subject'   => "drug_code_set:{$set}",
            'old_value' => ['count' => count($oldCodes)],
            'new_value' => [
                'count'   => count($newCodes),
                'added'   => array_values(array_diff($newCodes, $oldCodes)),
                'removed' => array_values(array_diff($oldCodes, $newCodes)),
            ],
        ]);

        return response()->json([
            'version'  => $this->sets->version($set),
            'count'    => count($newCodes),
            'overlaps' => $this->overlaps($set, $newCodes),
            'message'  => 'บันทึก'.$def['label'].'แล้ว',
        ]);
    }

    /**
     * Codes that are not in the hospital's own catalogue (drugs: `drugitems`; lab: `lab_items`).
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function unknownCodes(string $kind, array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        if ($kind === DrugCodeSetRegistry::KIND_LAB) {
            return array_values(array_filter($codes, fn (string $code) => ! $this->query->labItemExists($code)));
        }

        $known = array_flip(array_column($this->query->drugCatalog(), 'icode'));

        return array_values(array_filter($codes, fn (string $code) => ! isset($known[$code])));
    }

    /**
     * Drugs this set shares with other drug sets — a warning, not an error: the same drug can legitimately be in
     * several sets, but a pharmacist should know when it is.
     *
     * @param  list<string>  $codes
     * @return array<string, list<string>> code => labels of the other sets that hold it
     */
    private function overlaps(string $set, array $codes): array
    {
        if (DrugCodeSetRegistry::get($set)['kind'] !== DrugCodeSetRegistry::KIND_DRUG) {
            return [];
        }

        $out = [];
        foreach (DrugCodeSetRegistry::all() as $key => $def) {
            if ($key === $set || $def['kind'] !== DrugCodeSetRegistry::KIND_DRUG) {
                continue;
            }
            foreach (array_intersect($codes, $this->sets->codes($key)) as $code) {
                $out[$code][] = $def['label'];
            }
        }

        return $out;
    }

    /** What the editor script needs: KPIs, their sets, each set's saved items and version. */
    private function editorConfig(Collection $kpis): array
    {
        $setKeys = $kpis->flatMap(fn (array $row) => array_column($row['sets'], 'key'))->unique()->values();

        return [
            'urls' => [
                'catalog'  => route('drug-code-sets.catalog'),
                'labItems' => route('drug-code-sets.lab-items'),
                'update'   => route('drug-code-sets.update', ['set' => '__SET__']),
                'kpiActive' => route('drug-code-sets.kpi-active', ['code' => '__KPI__']),
            ],
            'kpis' => $kpis->map(fn (array $row) => [
                'code'       => $row['indicator']->code,
                'name'       => $row['indicator']->name,
                'configured' => $row['configured'],
                'active'     => (bool) $row['indicator']->is_active,
                'sets'       => array_column($row['sets'], 'key'),
            ])->values()->all(),
            'sets' => $setKeys->mapWithKeys(function (string $key) {
                $def = DrugCodeSetRegistry::get($key);

                return [$key => [
                    'key'       => $key,
                    'label'     => $def['label'],
                    'kind'      => $def['kind'],
                    'attribute' => $def['attribute'],
                    'max'       => $def['max'],
                    'kpis'      => $def['kpis'],
                    'version'   => $this->sets->version($key),
                    'items'     => collect($this->sets->items($key))
                        ->map(fn ($attr, $code) => ['code' => (string) $code, 'attribute' => $attr])->values()->all(),
                ]];
            })->all(),
        ];
    }
}
