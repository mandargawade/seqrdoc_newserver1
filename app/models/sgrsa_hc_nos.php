<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class sgrsa_hc_nos extends Model
{
    protected $table = 'alloted_hc_numbers';
	public $timestamps = false;
    protected $fillable = [
        'id', 'sgrsa_allotments_id', 'supplier_id', 'hc_number', 'supplier_allotments_id', 'agent_id', 'status', 'status_date'
    ];
}
