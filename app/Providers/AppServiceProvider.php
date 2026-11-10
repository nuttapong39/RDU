<?php

namespace App\Providers;

use App\DataSources\HosxpRduDataSource;
use App\DataSources\MockRduDataSource;
use App\DataSources\RduDataSourceInterface;
use App\DataSources\UnconfiguredRduDataSource;
use App\Database\ReadonlyConnection;
use App\Models\RduIndicator;
use App\Queries\Kpi\Antibiotic\AccidentWoundAntibioticQuery;
use App\Queries\Kpi\Antibiotic\AcuteDiarrheaAntibioticQuery;
use App\Queries\Kpi\Antibiotic\FreshAccidentWoundAntibioticQuery;
use App\Queries\Kpi\Antibiotic\NormalDeliveryAntibioticQuery;
use App\Queries\Kpi\Antibiotic\RespiratoryInfectionAntibioticQuery;
use App\Queries\Kpi\ChronicDisease\MetforminDiabetesQuery;
use App\Queries\Kpi\PatientSafety\AsthmaInhaledCorticosteroidQuery;
use App\Queries\Kpi\PatientSafety\ChildRespiratoryNonSedatingQuery;
use App\Queries\Kpi\PatientSafety\CkdNsaidQuery;
use App\Queries\Kpi\PatientSafety\ElderlyLongActingBenzodiazepineQuery;
use App\Queries\Kpi\PatientSafety\NsaidDuplicationQuery;
use App\Queries\Kpi\PatientSafety\RasDualBlockadeQuery;
use App\Services\DrugCodeSetService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HosxpRduDataSource::class, function ($app) {
            return new HosxpRduDataSource([
                'KPI01' => $app->make(AccidentWoundAntibioticQuery::class),
                'KPI02' => $app->make(FreshAccidentWoundAntibioticQuery::class),
                'KPI03' => $app->make(NormalDeliveryAntibioticQuery::class),
                'KPI04' => $app->make(RespiratoryInfectionAntibioticQuery::class),
                'KPI05' => $app->make(AcuteDiarrheaAntibioticQuery::class),
                'KPI07' => $app->make(NsaidDuplicationQuery::class),
                'KPI08' => $app->make(AsthmaInhaledCorticosteroidQuery::class),
                'KPI09' => $app->make(CkdNsaidQuery::class),
                'KPI10' => $app->make(MetforminDiabetesQuery::class),
                'KPI11' => $app->make(ChildRespiratoryNonSedatingQuery::class),
                'KPI12' => $app->make(RasDualBlockadeQuery::class),
                'KPI13' => $app->make(ElderlyLongActingBenzodiazepineQuery::class),
            ]);
        });

        $this->app->bind(RduDataSourceInterface::class, function ($app) {
            // Fake figures only ever in the test suite; an unconfigured app shows "no data", never mock numbers.
            if ($app->environment('testing')) {
                return $app->make(MockRduDataSource::class);
            }
            if (! $app->make(\App\Services\DatabaseConfigService::class)->hasConfig()) {
                return $app->make(UnconfiguredRduDataSource::class);
            }
            return $app->make(HosxpRduDataSource::class);
        });
    }

    public function boot(): void
    {
        // The HOSxP connection is built from rdu_database.json when first used, not at boot:
        // `config:cache` would otherwise write the decrypted password into bootstrap/cache/config.php,
        // and artisan commands that never touch HOSxP would need to read the file. Building it here
        // also re-attaches the read-only guard every time the connection is (re)made, e.g. after a purge.
        $this->app['db']->extend('rdu', function (array $config, string $name) {
            if (! $this->app->environment('testing')) {
                $configService = $this->app->make(\App\Services\DatabaseConfigService::class);
                if ($configService->hasConfig()) {
                    $config = array_merge($config, $configService->getConnectionConfig());
                }
            }

            $connection = $this->app['db.factory']->make($config, $name);
            ReadonlyConnection::installOn($connection);

            return $connection;
        });

        // The sidebar's "ภาพรวม N ตัวชี้วัด" follows the KPIs switched on (rdu_indicators.is_active).
        View::composer('layouts.dashboard', fn ($view) => $view->with('activeKpiCount', RduIndicator::active()->count()));

        Gate::define('manage-settings', fn ($user) => in_array(
            strtolower((string) $user->username),
            config('rdu.settings_admins', []),
            true
        ));

        // Admins always may; other users only when an admin granted it (admin/drug-code-editors).
        Gate::define('edit-drug-code-sets', fn ($user) => DrugCodeSetService::userCanEdit($user));
    }
}
