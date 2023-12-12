<?php

namespace App\Models\Demo;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
	protected $connection = 'mysql1';
    public $table="sites";
    public $primaryKey="site_id";
    public $fillable=['site_url','status','start_date','end_date','license_key'];
}
