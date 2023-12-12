<?php

namespace App\models\raisoni;

use Illuminate\Database\Eloquent\Model;

class DegreeMaster extends Model
{
    protected $table='degree_master';
    protected $fillable=['degree_name','is_active'];
}
