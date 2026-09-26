<?php

namespace Database\Factories;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;

class BreakTimeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attendance_id' => Attendance::factory(),
            'break_in' => fake()->dateTimeBetween('11:00:00', '13:00:00')->format('H:i:s'),
            'break_out' => fake()->dateTimeBetween('14:00:00', '16:00:00')->format('H:i:s'),
        ];
    }
}
