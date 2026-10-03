<?php

namespace Database\Factories;

use App\Models\AttendanceCorrectionRequest;
use App\Models\BreakTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class BreakTimeCorrectionRequestFactory extends Factory
{
    public function definition(): array
    {

        return [
            'attendance_correction_request_id' => AttendanceCorrectionRequest::factory(),
            'break_time_id' => null,
            'new_break_in' => null,
            'new_break_out' => null,
        ];
    }

    public function delete(BreakTime $breakTime): static
    {
        return $this->state(fn (array $attributes) => [
            'break_time_id' => $breakTime->id,
            'new_break_in' => null,
            'new_break_out' => null,
        ]);
    }

    public function only_break_in(BreakTime $breakTime): static
    {
        $newBreakIn = fake()->dateTimeBetween('10:00:00', '15:00:00');

        return $this->state(fn (array $attributes) => [
            'break_time_id' => $breakTime->id,
            'new_break_in' => $newBreakIn->format('H:i:s'),
            'new_break_out' => null,
        ]);
    }

    public function only_break_out(BreakTime $breakTime): static
    {
        return $this->state(fn (array $attributes) => [
            'break_time_id' => $breakTime->id,
            'new_break_in' => null,
            'new_break_out' => Carbon::parse($breakTime->break_in)
                ->addMinutes(fake()->numberBetween(45, 60))
                ->format('H:i:s'),
        ]);
    }

    public function break_in_and_out(): static
    {
        $newBreakIn = fake()->dateTimeBetween('10:00:00', '15:00:00');

        return $this->state(fn (array $attributes) => [
            'new_break_in' => $newBreakIn->format('H:i:s'),
            'new_break_out' => Carbon::instance($newBreakIn)
                ->addMinutes(fake()->numberBetween(45, 60))
                ->format('H:i:s'),
        ]);
    }
}
