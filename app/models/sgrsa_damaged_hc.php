<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class sgrsa_damaged_hc extends Model
{
    protected $table = 'damaged_hc';
	public $timestamps = false;
    protected $fillable = [
        'id', 'condition','hc_no_enter', 'hc_no_original', 'supplier_id', 'admin_id', 'alloted_hc_numbers_id', 'created_date'
    ];
}
