<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        $attendanceStatus = fake()->randomElement([
            'not_started',
            'working',
            'finished',
        ]);

        return [
            'user_id' => User::factory(),
            'date' => fake()->date(),

            'clock_in' => match ($attendanceStatus) {
                'not_started' => null,
                'working', 'finished' => fake()
                    ->dateTimeBetween('06:00:00', '09:00:00')
                    ->format('H:i:s'),
            },

            'clock_out' => match ($attendanceStatus) {
                'not_started', 'working' => null,
                'finished' => fake()
                    ->dateTimeBetween('17:00:00', '20:00:00')
                    ->format('H:i:s'),
            },

            'comment' => null,
        ];
    }
}
