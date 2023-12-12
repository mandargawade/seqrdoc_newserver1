<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    public $table="sites";
    public $primaryKey="site_id";
    public $fillable=['site_url','status'];
}
