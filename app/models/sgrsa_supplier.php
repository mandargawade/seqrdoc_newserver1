<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class sgrsa_supplier extends Model
{
    protected $table = 'suppliers';
	public $timestamps = false;
    protected $fillable = [
        'id', 'company_name', 'registration_no', 'pin_no', 'vat_no', 'po_box', 'code', 'town', 'tel_no', 'tel_no_two', 'email', 'company_logo', 'initials', 'publish', 'admin_id', 'created_date'
    ];
}
