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

    /**
     * マスアサイメント可能な属性を定義する。
     */
    protected $fillable = [
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    /**
     * ユーザーとのリレーションを定義する。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 休憩時間とのリレーションを定義する。
     */
    public function breakTimes(): HasMany
    {
        return $this->hasMany(BreakTime::class);
    }

    /**
     * 勤怠修正申請とのリレーションを定義する。
     */
    public function attendanceCorrectionRequests(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionRequest::class);
    }

    /**
     * 総休憩時間を取得する。
     */
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

    /**
     * 総労働時間を取得する。
     */
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

    /**
     * 総休憩時間を分単位で算出する。
     */
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
