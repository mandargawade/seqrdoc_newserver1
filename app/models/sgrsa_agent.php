<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class sgrsa_agent extends Model
{
    protected $table = 'agents';
	public $timestamps = false;
    protected $fillable = [
        'id', 'name', 'location', 'contact_no', 'email', 'publish', 'admin_id', 'supplier_id', 'created_date'
    ];
}