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

    protected $fillable = [
        'attendance_id',
        'new_clock_in',
        'new_clock_out',
        'comment',
        'status',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function breakTimeCorrectionRequests(): HasMany
    {
        return $this->hasMany(BreakTimeCorrectionRequest::class, 'attendance_correction_request_id');
    }

    protected function user(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attendance->user,
        );
    }

    protected function AttendanceRecord(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attendance,
        );
    }

    protected function approvalStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'pending'
                ? '承認待ち'
                : '承認済み',
        );
    }

    protected function applicationDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->created_at,
        );
    }

    protected function newDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attendance?->date
                ? Carbon::parse($this->attendance->date)
                : null,
        );
    }

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
