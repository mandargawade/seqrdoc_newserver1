<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class SuperApiTracker extends Model
{
    public $connection = "mysql2";
    public $timestamps = false;

    protected $table = 'api_tracker';

    protected $fillable = [
    	'id','client_id','request_url','function_name','request_method','header_parameters','response_time','request_parameters','response_parameters','response_time','satus','created','updated'
    ];

}
