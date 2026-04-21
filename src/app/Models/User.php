<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    // 役割を表す定数
    public const ROLE_ADMIN = 1;
    public const ROLE_USER = 2;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'role' => 'integer',
        'email_verified_at' => 'datetime',
    ];

    // ユーザーに紐づく勤怠一覧
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    // 管理者かどうかを判定する
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    // 一般ユーザーかどうかを判定する
    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }
}
