<?php

namespace Database\Factories;

use App\Models\RduIndicator;
use App\Models\RduIndicatorResult;
use Illuminate\Database\Eloquent\Factories\Factory;

class RduIndicatorResultFactory extends Factory
{
    protected $model = RduIndicatorResult::class;

    public function definition(): array
    {
        $numerator   = $this->faker->numberBetween(50, 200);
        $denominator = $this->faker->numberBetween($numerator, 300);
        $result      = $denominator > 0 ? round($numerator / $denominator * 100, 2) : null;

        return [
            'indicator_id'  => RduIndicator::first()?->id ?? 1,
            'year'          => 2568,
            'month'         => $this->faker->numberBetween(1, 12),
            'numerator'     => $numerator,
            'denominator'   => $denominator,
            'result'        => $result,
            'status'        => $this->faker->randomElement(['pass', 'warning', 'fail']),
            'calculated_at' => now(),
            'calculated_by' => null,
        ];
    }
}
