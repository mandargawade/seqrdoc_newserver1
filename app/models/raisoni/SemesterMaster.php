<?php

namespace App\models\raisoni;

use Illuminate\Database\Eloquent\Model;

class SemesterMaster extends Model
{
    protected $guard = 'admin';
    protected $table = 'semester_master';

    protected $fillable = [
        'semester_name','semester_full_name', 'is_active','created_at', 'updated_at'
    ];
}
