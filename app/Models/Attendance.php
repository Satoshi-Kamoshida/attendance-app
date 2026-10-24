<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function breakTimes(): HasMany
    {
        return $this->hasMany(BreakTime::class);
    }

    public function attendanceCorrectionRequests(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionRequest::class);
    }

    public function getTotalBreakTimeAttribute(): string
    {
        $totalMinutes = $this->breakTimes
            ->filter(function ($breakTime) {
                return $breakTime->break_in !== null
                    && $breakTime->break_out !== null;
            })
            ->sum(function ($breakTime) {
                $breakIn = Carbon::parse($breakTime->break_in);
                $breakOut = Carbon::parse($breakTime->break_out);

                return $breakOut->diffInMinutes($breakIn);
            });

        return sprintf(
            '%d:%02d',
            intdiv($totalMinutes, 60),
            $totalMinutes % 60
        );
    }

    public function getTotalTimeAttribute(): ?string
    {
        if ($this->clock_in === null || $this->clock_out === null) {
            return null;
        }

        $clockIn = Carbon::parse($this->clock_in);
        $clockOut = Carbon::parse($this->clock_out);

        $totalMinutes = $clockOut->diffInMinutes($clockIn)
            - $this->getTotalBreakMinutes();

        if ($totalMinutes < 0) {
            return null;
        }

        return sprintf(
            '%d:%02d',
            intdiv($totalMinutes, 60),
            $totalMinutes % 60
        );
    }

    private function getTotalBreakMinutes(): int
    {
        return $this->breakTimes
            ->filter(function ($breakTime) {
                return $breakTime->break_in !== null
                    && $breakTime->break_out !== null;
            })
            ->sum(function ($breakTime) {
                $breakIn = Carbon::parse($breakTime->break_in);
                $breakOut = Carbon::parse($breakTime->break_out);

                return $breakOut->diffInMinutes($breakIn);
            });
    }
}
