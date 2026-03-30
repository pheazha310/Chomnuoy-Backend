<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationDocument extends Model
{
    use HasFactory;

    protected $table = 'organization_document';

    protected $guarded = [];

    public $timestamps = false;
}
