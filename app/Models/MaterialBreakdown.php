<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialBreakdown extends Model
{
    use HasFactory;

    protected $table = 'material_breakdowns';

    protected $guarded = [];
}
