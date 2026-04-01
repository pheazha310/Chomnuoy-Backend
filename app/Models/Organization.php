<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $table = 'organizations';

    protected $fillable = [
        'name',
        'email',
        'password',
        'category_id',
        'location',
        'latitude',
        'longitude',
        'description',
        'verified_status',
        'avatar_path',
    ];

    protected $casts = [
        'latitude' => 'decimal:6',
        'longitude' => 'decimal:6',
    ];

    protected $hidden = ['password'];
    protected $appends = ['avatar_url'];
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
