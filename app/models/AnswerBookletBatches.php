<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class AnswerBookletBatches extends Model
{
    protected $table = 'answer_booklet_batches';

    protected $fillable = [
    	'id','prefix','booklet_size','start_serial_no','end_serial_no','quantity','admin_id','created_at','updated_at'
    ];

    public $timestamps = true;
}
