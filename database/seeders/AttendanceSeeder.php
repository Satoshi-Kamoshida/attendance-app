<?php

namespace Database\Seeders;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        for ($monthOffset = 5; $monthOffset >= 1; $monthOffset--) {
            $month = now()->setDate(2026, 11, 1)
                ->subMonths($monthOffset);

            $date = $month->copy()->startOfMonth();
            $createdDays = 0;

            while ($createdDays < 15) {
                if ($date->isWeekday()) {
                    Attendance::factory()->create([
                        'user_id' => 1,
                        'date' => $date->toDateString(),
                        'clock_in' => '09:00:00',
                        'clock_out' => '18:00:00',
                    ]);

                    $createdDays++;
                }

                $date->addDay();
            }
        }

        $patterns = [
            ...array_fill(0, 10, [
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]),
            ...array_fill(0, 3, [
                'clock_in' => '09:00:00',
                'clock_out' => '20:00:00',
            ]),
            ...array_fill(0, 2, [
                'clock_in' => '09:30:00',
                'clock_out' => '18:00:00',
            ]),
            [
                'clock_in' => '09:00:00',
                'clock_out' => '17:00:00',
            ],
            [
                'clock_in' => '08:00:00',
                'clock_out' => '21:00:00',
            ],
        ];

        $date = Carbon::create(2026, 11, 1);

        foreach ($patterns as $pattern) {
            while (! $date->isWeekday()) {
                $date->addDay();
            }

            Attendance::factory()->create([
                'user_id' => 1,
                'date' => $date->toDateString(),
                'clock_in' => $pattern['clock_in'],
                'clock_out' => $pattern['clock_out'],
            ]);

            $date->addDay();
        }

        $date = Carbon::create(2026, 9, 1);
        $endDate = Carbon::create(2026, 11, 30);

        while ($date->lte($endDate)) {
            Attendance::factory()->create([
                'user_id' => 2,
                'date' => $date->toDateString(),
            ]);

            $date->addDay();
        }
    }
}
