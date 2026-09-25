<?php

namespace App\Providers;

use App\DataSources\HosxpRduDataSource;
use App\DataSources\MockRduDataSource;
use App\DataSources\RduDataSourceInterface;
use App\DataSources\UnconfiguredRduDataSource;
use App\Database\ReadonlyConnection;
use App\Queries\Kpi\Antibiotic\AccidentWoundAntibioticQuery;
use App\Queries\Kpi\Antibiotic\AcuteDiarrheaAntibioticQuery;
use App\Queries\Kpi\Antibiotic\DiarrheaNoAntibioticQuery;
use App\Queries\Kpi\Antibiotic\NormalDeliveryAntibioticQuery;
use App\Queries\Kpi\Antibiotic\RespiratoryInfectionAntibioticQuery;
use App\Queries\Kpi\ChronicDisease\DmHba1cControlledQuery;
use App\Queries\Kpi\DrugQuality\AntimicrobialValueQuery;
use App\Queries\Kpi\DrugQuality\GenericDrugValueQuery;
use App\Queries\Kpi\DrugQuality\NationalDrugListValueQuery;
use App\Queries\Kpi\PatientSafety\CorticosteroidInjectionQuery;
use App\Queries\Kpi\PatientSafety\ElderlyNsaidsQuery;
use App\Queries\Kpi\PatientSafety\ElderlyPolypharmacyQuery;
use App\Queries\Kpi\PatientSafety\VitB1InjectionQuery;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HosxpRduDataSource::class, function ($app) {
            return new HosxpRduDataSource([
                'KPI01' => $app->make(AccidentWoundAntibioticQuery::class),
                'KPI02' => $app->make(DiarrheaNoAntibioticQuery::class),
                'KPI03' => $app->make(NormalDeliveryAntibioticQuery::class),
                'KPI04' => $app->make(RespiratoryInfectionAntibioticQuery::class),
                'KPI05' => $app->make(AcuteDiarrheaAntibioticQuery::class),
                'KPI06' => $app->make(NationalDrugListValueQuery::class),
                'KPI07' => $app->make(GenericDrugValueQuery::class),
                'KPI08' => $app->make(AntimicrobialValueQuery::class),
                'KPI09' => $app->make(ElderlyNsaidsQuery::class),
                'KPI10' => $app->make(VitB1InjectionQuery::class),
                'KPI11' => $app->make(CorticosteroidInjectionQuery::class),
                'KPI12' => $app->make(ElderlyPolypharmacyQuery::class),
                'KPI13' => $app->make(DmHba1cControlledQuery::class),
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
        if (! $this->app->environment('testing')) {
            $configService = $this->app->make(\App\Services\DatabaseConfigService::class);
            if ($configService->hasConfig()) {
                config(['database.connections.rdu' => $configService->getConnectionConfig()]);
            }
        }
        ReadonlyConnection::install();

        Gate::define('manage-settings', fn ($user) => in_array(
            strtolower((string) $user->username),
            config('rdu.settings_admins', []),
            true
        ));
    }
}
