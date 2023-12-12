<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class sgrsa_recall_informations extends Model
{
    protected $table = 'recall_informations';
	public $timestamps = false;
    protected $fillable = [
        'id', 'certificate_no', 'vehicle_reg_no', 'chassis_no', 'type_of_governor', 'unit_sr_no', 'supplier', 'supplier_id', 'make', 'model', 'hc_no', 'date_of_installation', 'date_of_expiry', 'cno', 'record_unique_id', 'publish', 'admin_id', 'file_name', 'encryptedString', 'created_date'
    ];
}
