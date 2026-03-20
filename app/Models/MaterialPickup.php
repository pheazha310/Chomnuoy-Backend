<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialPickup extends Model
{
    use HasFactory;

    protected $table = 'material_pickups';

    protected $guarded = [];

    public $timestamps = false;
}
