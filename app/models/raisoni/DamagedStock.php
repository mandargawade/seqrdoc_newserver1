<?php

namespace App\models\raisoni;

use Illuminate\Database\Eloquent\Model;

class DamagedStock extends Model
{
    protected $guard = 'admin';
    protected $table = 'damaged_stationary';

    protected $fillable = [
        'card_category','serial_no', 'type','remark','exam','degree','semester','registration_no','branch','added_by','created_at', 'updated_at'
    ];
}
