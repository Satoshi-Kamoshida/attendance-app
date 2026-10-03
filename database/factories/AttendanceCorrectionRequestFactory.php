<?php

namespace Database\Factories;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceCorrectionRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attendance_id' => Attendance::factory(),
            'comment' => fake('ja_JP')->randomElement([
                '勤怠を修正しました。',
                '打刻時間を間違えました。',
                '勤怠を修正致します。',
            ]),
            'status' => fake()->randomElement(['pending', 'approved']),
        ];
    }

    public function delete(): static
    {
        return $this->state(fn (array $attributes) => [
            'new_clock_in' => null,
            'new_clock_out' => null,
        ]);
    }

    public function only_clock_in(): static
    {
        return $this->state(fn (array $attributes) => [
            'new_clock_in' => fake()
                ->dateTimeBetween('06:00:00', '09:00:00')
                ->format('H:i:s'),
            'new_clock_out' => null,
        ]);
    }

    public function only_clock_out(): static
    {
        return $this->state(fn (array $attributes) => [
            'new_clock_in' => null,
            'new_clock_out' => fake()
                ->dateTimeBetween('17:00:00', '20:00:00')
                ->format('H:i:s'),
        ]);
    }

    public function clock_in_and_out(): static
    {
        return $this->state(fn (array $attributes) => [
            'new_clock_in' => fake()
                ->dateTimeBetween('06:00:00', '09:00:00')
                ->format('H:i:s'),
            'new_clock_out' => fake()
                ->dateTimeBetween('17:00:00', '20:00:00')
                ->format('H:i:s'),
        ]);
    }
}
