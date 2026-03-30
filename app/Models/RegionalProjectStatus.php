<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegionalProjectStatus extends Model
{
    use HasFactory;

    protected $table = 'regional_project_statuses';

    protected $guarded = [];
}
