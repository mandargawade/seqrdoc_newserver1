<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class SbScannedHistory extends Model
{
    protected $table = 'sb_scanned_history';

    protected $fillable = [
    	'id','date_time','device_type','scanned_data','scan_by','scan_result'
    ];

    public $timestamps = false;
}

