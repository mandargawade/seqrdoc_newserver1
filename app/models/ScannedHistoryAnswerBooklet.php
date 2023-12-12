<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class ScannedHistoryAnswerBooklet extends Model
{
    protected $table = 'scanned_history_answer_booklet';

    protected $fillable = [
    	'id','date_time','device_type','scanned_data','scan_by','scan_result','document_id','document_status','user_type'
    ];

    public $timestamps = true;
}
