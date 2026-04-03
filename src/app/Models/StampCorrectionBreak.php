<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StampCorrectionBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'stamp_correction_request_id',
        'requested_break_start_at',
        'requested_break_end_at',
    ];

    protected $casts = [
        'requested_break_start_at' => 'datetime',
        'requested_break_end_at' => 'datetime',
    ];

    // この休憩が紐づく打刻修正申請
    public function stampCorrectionRequest()
    {
        return $this->belongsTo(StampCorrectionRequest::class);
    }
}
