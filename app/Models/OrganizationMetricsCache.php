<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationMetricsCache extends Model
{
    use HasFactory;

    protected $table = 'organization_metrics_cache';

    protected $guarded = [];
}
