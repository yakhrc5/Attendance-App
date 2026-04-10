<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StampCorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'requested_clock_in_at',
        'requested_clock_out_at',
        'reason',
        'approved_at',
    ];

    protected $casts = [
        'requested_clock_in_at' => 'datetime',
        'requested_clock_out_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    // この打刻修正申請に紐づく勤怠
    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    // この打刻修正申請に紐づく休憩一覧
    public function stampCorrectionBreaks()
    {
        return $this->hasMany(StampCorrectionBreak::class);
    }
}
