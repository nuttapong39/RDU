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

    /** @var array<string, ?\Illuminate\Support\Carbon> */
    private array $changeCache = [];

    /** @var array<string, string> */
    private array $versionCache = [];

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

    /**
     * The latest time a pharmacist saved any set this KPI reads, on the settings page. A result calculated before
     * it was counted with older drug codes, so it must not be reused. Null when none was ever edited there (the
     * one-time import does not count: it is the baseline).
     */
    public function latestChangeAt(string $kpiCode): ?\Illuminate\Support\Carbon
    {
        if (! array_key_exists($kpiCode, $this->changeCache)) {
            $latest = DrugCodeSet::whereIn('key', DrugCodeSetRegistry::forKpi($kpiCode))->max('changed_at');
            $this->changeCache[$kpiCode] = $latest === null ? null : \Illuminate\Support\Carbon::parse($latest);
        }

        return $this->changeCache[$kpiCode];
    }

    /** Goes into a KPI's cache keys, so a cached count is never reused after one of its sets is saved. */
    public function cacheVersion(string $kpiCode): string
    {
        return $this->versionCache[$kpiCode] ??= implode('-', array_map(
            fn (string $key) => $this->version($key),
            DrugCodeSetRegistry::forKpi($kpiCode)
        )) ?: '0';
    }

    public function flush(): void
    {
        $this->itemsCache  = [];
        $this->changeCache = [];
        $this->versionCache = [];
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
