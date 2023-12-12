<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class sgrsa_supplier_allotment extends Model
{
    protected $table = 'supplier_allotments';
	public $timestamps = false;
    protected $fillable = [
        'id', 'issue_date','agent_id', 'hc_from', 'hc_to', 'quantity', 'publish', 'supplier_id', 'admin_id', 'sgrsa_allotments_id', 'record_unique_id', 'created_date'
    ];
}
