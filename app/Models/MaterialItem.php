<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialItem extends Model
{
    use HasFactory;

    protected $table = 'material_items';

    protected $guarded = [];

    public $timestamps = false;
}
