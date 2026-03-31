<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'phone', 'email', 'password', 'status', 'role_id', 'avatar_path', 'last_seen_at'];

    protected $hidden = ['password'];

    protected $appends = ['avatar_url'];

    public const UPDATED_AT = null;

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar_path) {
            return null;
        }

        return asset(Storage::url($this->avatar_path));
    }
}
