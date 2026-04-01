<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
