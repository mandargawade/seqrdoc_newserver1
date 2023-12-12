<?php

namespace App\models\raisoni;

use Illuminate\Database\Eloquent\Model;

class StationaryStock extends Model
{
    protected $guard = 'admin';
    protected $table = 'stationary_stock';

    protected $fillable = [
        'card_category','academic_year', 'date_of_received','serial_no_from','serial_no_to','quantity','added_by','created_at', 'updated_at'
    ];
}
