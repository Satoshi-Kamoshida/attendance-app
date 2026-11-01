<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BreakTime extends Model
{
    use HasFactory;

    /**
     * マスアサインメント可能な属性を定義する。
     */
    protected $fillable = [
        'break_in',
        'break_out',
    ];

    /**
     * 勤怠とのリレーションを定義する。
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * 休憩修正申請とのリレーションを定義する。
     */
    public function breakTimeCorrectionRequests(): HasMany
    {
        return $this->hasMany(BreakTimeCorrectionRequest::class);
    }
}
