<?php

namespace App\models\raisoni;

use Illuminate\Database\Eloquent\Model;

class SectionMaster extends Model
{
    protected $guard = 'admin';
    protected $table='section_master';
    protected $fillable=['section_name','is_active'];
}
