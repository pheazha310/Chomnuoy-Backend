<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileActivity extends Model
{
    use HasFactory;

    protected $table = 'profile_activities';

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];
}
