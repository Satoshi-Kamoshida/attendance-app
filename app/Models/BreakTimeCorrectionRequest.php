<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BreakTimeCorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
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
