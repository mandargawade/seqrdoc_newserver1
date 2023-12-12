<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class StudentHistory extends Model
{
    protected $table = 'student_history';

    protected $fillable = [
        'date_time','device_type', 'scanned_data','scan_by', 'scan_result',
    ];

    public $timestamps = true;
}
