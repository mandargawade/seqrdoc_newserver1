<?php

namespace App\models\raisoni;

use Illuminate\Database\Eloquent\Model;

class BranchMaster extends Model
{
    protected $guard = 'admin';
    protected $table = 'branch_master';

    protected $fillable = [
        'branch_name_long','branch_name_short','degree_id', 'is_active','created_at', 'updated_at'
    ];
}
