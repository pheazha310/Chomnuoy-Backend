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

        return asset(Storage::url($this->avatar_path));
    }
}
