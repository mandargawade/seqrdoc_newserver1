<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class ApiAuthIpAddresses extends Model
{
    protected $table = 'api_auth_ip_addresses';

    protected $fillable = [
    	'id','ip_address','publish','admin_id','created','updated'
    ];

    public $timestamps = false;
}
