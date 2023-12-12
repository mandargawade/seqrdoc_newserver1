<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class IDCardStatus extends Model
{
    protected $table = 'id_card_status';

    protected $fillable = [
    	'template_name','excel_sheet','request_number','rows','status','created_on',
    ];

    public $timestamps = false;

}
