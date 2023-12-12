<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class DBDetails extends Model
{
    protected $table = 'db_details';

    protected $fillable = [
    	'template_id','db_name','db_host_address','username','password','port','table_name'
    ];

    public $timestamps = true;
}
