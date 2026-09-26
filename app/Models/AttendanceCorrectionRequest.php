<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'new_clock_in',
        'new_clock_out',
        'comment',
        'status',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function breakTimeCorrectionRequests()
    {
        return $this->hasMany(BreakTimeCorrectionRequest::class);
    }
}
