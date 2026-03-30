<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SourceBreakdown extends Model
{
    use HasFactory;

    protected $table = 'source_breakdowns';

    protected $guarded = [];
}
