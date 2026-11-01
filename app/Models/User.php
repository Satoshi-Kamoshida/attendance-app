<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * マスアサインメント可能な属性を定義する。
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'admin_status',
    ];

    /**
     * JSONなどへの変換時に非表示にする属性を定義する。
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * 属性の型変換を定義する。
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'admin_status' => 'boolean',
    ];

    /**
     * 勤怠とのリレーションを定義する。
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
