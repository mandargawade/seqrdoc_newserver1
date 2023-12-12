<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class WebsiteDetail extends Model
{
    public $table="website_details";

    public $fillable=['website_url','start_date','end_date','license_key','db_name','db_host_address','username','password','port','table_name','status'];
}
