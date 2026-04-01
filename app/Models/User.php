<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'title', 'bio', 'phone', 'email', 'password', 'status', 'role_id', 'avatar_path', 'last_seen_at', 'two_factor_enabled', 'location', 'website', 'linkedin_url', 'skills', 'connections_count', 'project_reviews_count', 'network_rank'];

    protected $hidden = ['password'];

    protected $appends = ['avatar_url'];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'two_factor_enabled' => 'boolean',
    ];

    public const UPDATED_AT = null;

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar_path) {
            return null;
        }

        $segments = array_map('rawurlencode', explode('/', trim($this->avatar_path, '/')));
        $baseUrl = rtrim((string) config('app.url'), '/');

        return $baseUrl . '/api/files/' . implode('/', $segments);
    }
}
