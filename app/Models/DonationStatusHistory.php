<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonationStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'donation_status_history';

    protected $guarded = [];

    public $timestamps = false;
}
