<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class BlockChainApiTracker extends Model
{
    protected $table = 'bc_api_tracker';

    protected $fillable = [
    	'id','client_ip','request_url','request_method','status','header_parameters','request_parameters','response','api_name','response_time','created_at','updated_at'
    ];

    public $timestamps = false;
}
