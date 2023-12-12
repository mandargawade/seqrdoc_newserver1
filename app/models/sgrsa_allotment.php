<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class sgrsa_allotment extends Model
{
    protected $table = 'sgrsa_allotments';
	public $timestamps = false;
    protected $fillable = [
        'id', 'issue_date', 'supplier_id', 'hc_from', 'hc_to', 'quantity', 'publish', 'admin_id', 'created_date', 'supplier_reply', 'supplier_reply_date'
    ];
}
