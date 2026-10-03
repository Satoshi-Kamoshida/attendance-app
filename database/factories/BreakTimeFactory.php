<?php

namespace Database\Factories;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class BreakTimeFactory extends Factory
{
    public function definition(): array
    {
        $breakTimeStatus = fake()->randomElement([
            'breaking',
            'break_finished',
        ]);

        $breakIn = fake()->dateTimeBetween('10:00:00', '15:00:00');

        return [
            'attendance_id' => Attendance::factory(),
            'break_in' => $breakIn->format('H:i:s'),
            'break_out' => match ($breakTimeStatus) {
                'breaking' => null,
                'break_finished' => Carbon::instance($breakIn)
                    ->addMinutes(fake()->numberBetween(45, 60))
                    ->format('H:i:s'),
            },
        ];
    }
}
