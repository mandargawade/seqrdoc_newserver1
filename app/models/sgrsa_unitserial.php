<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class sgrsa_unitserial extends Model
{
    protected $table = 'unit_sr_numbers';
	public $timestamps = false;
    protected $fillable = [
        'id', 'governor_type_id', 'reg_number', 'publish', 'used', 'admin_id', 'supplier_id', 'created_date'
    ];
}
