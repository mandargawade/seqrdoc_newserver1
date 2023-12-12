<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class AnswerBookletData extends Model
{
    protected $table = 'answer_booklet_data';

    protected $fillable = [
    	'id','serial_no','qr_data','key','metadata1','status','publish','scan_count','created_at','updated_at','site_id'
    ];

    public $timestamps = true;
}
