<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BreakTimeCorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_correction_request_id',
        'break_time_id',
        'new_break_in',
        'new_break_out',
    ];

    public function breakTime()
    {
        return $this->belongsTo(BreakTime::class);
    }

    public function attendanceCorrectionRequest()
    {
        return $this->belongsTo(AttendanceCorrectionRequest::class);
    }
}
