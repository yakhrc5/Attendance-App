<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    use HasFactory, Notifiable;

    /**
     * 一括代入を許可するカラム
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * 配列/JSON 変換時に隠すカラム
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // 管理者が承認した打刻修正申請一覧

    public function approvedStampCorrectionRequests()
    {
        return $this->hasMany(
            StampCorrectionRequest::class,
            'approved_by_admin_id'
        );
    }
}

