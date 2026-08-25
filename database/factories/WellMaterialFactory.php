<?php

namespace Database\Factories;

use App\Models\WellMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WellMaterial>
 */
class WellMaterialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $assets = ['BS', 'EXP', 'MH'];

        $wells = [
            'IE#6Y', 'IE#1', 'IN-8z', 'VSEA#12zH', 'VSEA#2yH', 'VSEA#16zH',
            'HJ-8Y', 'HJ-1Z', 'HJ-6Z', 'S1-4-9z', 'S1-4-3z', 'S1-4-2y',
            'IB-7y', 'IB-4zH', 'IB-3z', 'B12_1P#2Z', 'HA-3Z', 'SA-5z',
            'B12_18P2#1z', 'N22-5z', 'N22-3zH',
        ];

        $items = ['Item A', 'Item B', 'Item C', 'Item D'];

        return [
            'asset_name' => $this->faker->randomElement($assets),
            'well_name'  => $this->faker->randomElement($wells),
            'hptp'       => $this->faker->randomElement(['HPTP', 'Non-HPTP']),
            'item_name'  => $this->faker->randomElement($items),
        ];
    }
}
