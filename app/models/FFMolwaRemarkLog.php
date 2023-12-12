<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class FFMolwaRemarkLog extends Model
{
    protected $table = 'molwa_ff_remarks';
    public $timestamps = false;
    protected $fillable = [
    	'record_id',
    	'ff_id',
        'remark',
        'created_at'
    ];
}
