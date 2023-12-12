<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class sgrsa_governor extends Model
{
    protected $table = 'type_of_governor';
	public $timestamps = false;
    protected $fillable = [
        'id', 'type_name', 'publish', 'admin_id', 'supplier_id', 'created_date'
    ];
}
