<?php

namespace App\models\raisoni;

use Illuminate\Database\Eloquent\Model;

class SessionsMaster extends Model
{
    protected $table='exam_master';
    protected $fillable=['session_name','is_active'];
}
