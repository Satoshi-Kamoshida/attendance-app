<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class AttendanceCorrectionRequest extends Model
{
    use HasFactory;

    /**
     * マスアサイメント可能な属性を定義する。
     */
    protected $fillable = [
        'attendance_id',
        'new_clock_in',
        'new_clock_out',
        'comment',
        'status',
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
        return $this->hasMany(BreakTimeCorrectionRequest::class, 'attendance_correction_request_id');
    }

    /**
     * 申請者のユーザー情報を取得する。
     */
    protected function user(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attendance->user,
        );
    }

    /**
     * 対象の勤怠レコードを取得する。
     */
    protected function AttendanceRecord(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attendance,
        );
    }

    /**
     * 承認状態を日本語で取得する。
     */
    protected function approvalStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'pending'
                ? '承認待ち'
                : '承認済み',
        );
    }

    /**
     * 申請日時を取得する。
     */
    protected function applicationDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->created_at,
        );
    }

    /**
     * 修正対象の日付を取得する。
     */
    protected function newDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attendance?->date
                ? Carbon::parse($this->attendance->date)
                : null,
        );
    }

    /**
     * 修正申請された休憩時間を取得する。
     */
    protected function proposalBreaks(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->breakTimeCorrectionRequests
                ->map(fn ($breakRequest) => (object) [
                    'break_in' => $breakRequest->new_break_in,
                    'break_out' => $breakRequest->new_break_out,
                ]),
        );
    }
}
