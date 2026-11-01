<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakTimeCorrectionRequest extends Model
{
    use HasFactory;

    /**
     * マスアサインメント可能な属性を定義する。
     */
    protected $fillable = [
        'attendance_correction_request_id',
        'break_time_id',
        'new_break_in',
        'new_break_out',
    ];

    /**
     * 休憩とのリレーションを定義する。
     */
    public function breakTime(): BelongsTo
    {
        return $this->belongsTo(BreakTime::class);
    }

    /**
     * 勤怠修正申請とのリレーションを定義する。
     */
    public function attendanceCorrectionRequest(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrectionRequest::class);
    }
}
