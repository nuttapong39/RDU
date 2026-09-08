<?php

namespace App\Providers;

use App\DataSources\HosxpRduDataSource;
use App\DataSources\MockRduDataSource;
use App\DataSources\RduDataSourceInterface;
use App\Database\ReadonlyConnection;
use App\Queries\Kpi\Antibiotic\CapAppropriateAntibioticQuery;
use App\Queries\Kpi\Antibiotic\DiarrheaNoAntibioticQuery;
use App\Queries\Kpi\Antibiotic\IpdAntibioticUseQuery;
use App\Queries\Kpi\Antibiotic\UriNoAntibioticQuery;
use App\Queries\Kpi\Antibiotic\WoundNoAntibioticQuery;
use App\Queries\Kpi\ChronicDisease\DmHba1cControlledQuery;
use App\Queries\Kpi\DrugQuality\AntimicrobialValueQuery;
use App\Queries\Kpi\DrugQuality\GenericDrugValueQuery;
use App\Queries\Kpi\DrugQuality\NationalDrugListValueQuery;
use App\Queries\Kpi\PatientSafety\CorticosteroidInjectionQuery;
use App\Queries\Kpi\PatientSafety\ElderlyNsaidsQuery;
use App\Queries\Kpi\PatientSafety\ElderlyPolypharmacyQuery;
use App\Queries\Kpi\PatientSafety\VitB1InjectionQuery;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HosxpRduDataSource::class, function ($app) {
            return new HosxpRduDataSource([
                'KPI01' => $app->make(UriNoAntibioticQuery::class),
                'KPI02' => $app->make(DiarrheaNoAntibioticQuery::class),
                'KPI03' => $app->make(WoundNoAntibioticQuery::class),
                'KPI04' => $app->make(IpdAntibioticUseQuery::class),
                'KPI05' => $app->make(CapAppropriateAntibioticQuery::class),
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
            $configService = $app->make(\App\Services\DatabaseConfigService::class);
            if ($app->environment('testing') || ! $configService->hasConfig()) {
                return $app->make(MockRduDataSource::class);
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
    }
}
