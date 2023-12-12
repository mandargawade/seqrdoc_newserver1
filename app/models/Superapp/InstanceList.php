<?php

namespace App\models\Superapp;

use Illuminate\Database\Eloquent\Model;

class InstanceList extends Model
{
	protected $connection = 'mysql2';
    protected $table = 'instance_list';

    public $timestamps = false;

    protected $fillable = ['id','instance_name','base_url','publish','created_date_time','updated_date_time'];
} 