<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class FreedomFighterList extends Model
{
    protected $table = 'freedom_fighter_list';

    protected $fillable = [
        'ff_id', 'ff_name', 'father_or_husband_name', 'mother_name', 'is_alive', 'post_office', 'post_code', 'district', 'upazila_or_thana', 'village_or_ward', 'nid', 'ghost_image_code', 'ff_photo', 'ffl_id', 'record_unique_id', 'created_at', 'updated_at', 'generated_at', 'completed_at', 'reimported_at', 'c_generated_at', 'c_completed_at', 'c_reimported_at', 'admin_id', 'g_admin_id', 'c_admin_id', 'icc_admin_id', 'cc_admin_id', 'r_admin_id', 'active', 'import_flag', 'publish'
    ];
}
