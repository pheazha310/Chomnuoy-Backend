<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonationTrend extends Model
{
    use HasFactory;

    protected $table = 'donation_trends';

    protected $guarded = [];

    public const UPDATED_AT = null;
}
