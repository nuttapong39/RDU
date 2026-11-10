<?php

namespace App\Services;

use App\Models\DrugCodeSet;
use App\Models\DrugCodeSetEditor;
use App\Models\User;
use App\Support\DrugCodeSetRegistry;
use Illuminate\Support\Facades\Gate;

/** Reads the hospital's drug code sets from rdu_dashboard. Saving comes with the settings page (Phase 3). */
class DrugCodeSetService
{
    /** @var array<string, array<string, ?string>> */
    private array $itemsCache = [];

    /** code => attribute (null when the set has none), in the order the pharmacist listed them; empty when never configured. */
    public function items(string $key): array
    {
        DrugCodeSetRegistry::get($key);

        return $this->itemsCache[$key] ??= DrugCodeSet::find($key)
            ?->items()->orderBy('id')->pluck('attribute', 'code')->all() ?? [];
    }

    /** @return list<string> */
    public function codes(string $key): array
    {
        return array_map('strval', array_keys($this->items($key)));
    }

    /** 0 = the set has never been saved; a saved set starts at 1. */
    public function version(string $key): int
    {
        DrugCodeSetRegistry::get($key);

        return DrugCodeSet::find($key)?->version ?? 0;
    }

    /** When a pharmacist last saved the set on the settings page; null for a set never edited there (e.g. only imported). */
    public function changedAt(string $key): ?\Illuminate\Support\Carbon
    {
        DrugCodeSetRegistry::get($key);

        return DrugCodeSet::find($key)?->changed_at;
    }

    public function isConfigured(string $key): bool
    {
        return $this->items($key) !== [];
    }

    /** A KPI is configured when every set it needs has at least one item. A KPI with no sets (e.g. KPI06) counts as configured: callers must only ask about KPIs in the registry. */
    public function isKpiConfigured(string $kpiCode): bool
    {
        return $this->missingSets($kpiCode) === [];
    }

    /** The sets a KPI still lacks, for the settings page ("ยังขาด ..."). @return list<string> */
    public function missingSets(string $kpiCode): array
    {
        return array_values(array_filter(
            DrugCodeSetRegistry::forKpi($kpiCode),
            fn (string $key) => ! $this->isConfigured($key)
        ));
    }

    public function flush(): void
    {
        $this->itemsCache = [];
    }

    /** May this user edit drug code sets? Admins always can; others only when an admin granted it. */
    public static function userCanEdit(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return Gate::forUser($user)->allows('manage-settings')
            || DrugCodeSetEditor::where('user_id', $user->id)->exists();
    }
}
