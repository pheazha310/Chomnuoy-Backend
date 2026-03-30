<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationHistory extends Model
{
    use HasFactory;

    protected $table = 'organization_history';

    protected $guarded = [];

    public const UPDATED_AT = null;
}
