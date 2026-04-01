<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'phone', 'email', 'password', 'status', 'role_id', 'avatar_path', 'last_seen_at'];

    protected $hidden = ['password'];

    protected $appends = ['avatar_url'];

    public const UPDATED_AT = null;

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar_path) {
            return null;
        }

        $url = Storage::disk('public')->url($this->avatar_path);

        if (str_starts_with((string) config('app.url'), 'https://') && str_starts_with($url, 'http://')) {
            return preg_replace('/^http:\/\//i', 'https://', $url, 1) ?? $url;
        }

        return $url;
    }
}
