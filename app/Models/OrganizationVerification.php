<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationVerification extends Model
{
    use HasFactory;

    protected $table = 'organization_verifications';

    protected $guarded = [];

    public $timestamps = false;
}
