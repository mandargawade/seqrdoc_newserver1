<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class ScannedHistory extends Model
{
    protected $table = 'scanned_history';

    protected $fillable = [
    	'id','date_time','device_type','scanned_data','scan_by','scan_result','document_id','document_status'
    ];

    public $timestamps = false;
}
