<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in_at',
        'clock_out_at',
    ];

    protected $casts = [
        'work_date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
    ];

    // 勤怠に紐づくユーザー
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // 勤怠に紐づく休憩一覧
    public function attendanceBreaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    // 勤怠に紐づく打刻修正申請一覧
    public function stampCorrectionRequests(): HasMany
    {
        return $this->hasMany(StampCorrectionRequest::class);
    }
}
