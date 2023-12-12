<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class DbDetail extends Model
{
    public $table='db_details';

    public $fillable=['template_id','db_name','db_host_address','username','password','port','table_name'];
}
