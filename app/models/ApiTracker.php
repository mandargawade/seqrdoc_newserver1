<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class ApiTracker extends Model
{
    protected $table = 'api_tracker';

    protected $fillable = [
    	'id','client_ip','request_url','request_method','status','header_parameters','request_parameters','response_parameters','response_time','created'
    ];

    public $timestamps = false;
}
