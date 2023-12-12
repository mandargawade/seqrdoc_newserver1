<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class ImportLogDetail extends Model
{
    protected $table = 'import_log_details';

    protected $fillable = [
        'name', 'per_page', 'page_no', 'is_alive', 'district_id', 'upazila_id', 'category_ids', 'fresh_records', 'repeat_records', 'idcard_status', 'certificate_status', 'record_unique_id', 'created_at', 'updated_at', 'generated_at', 'completed_at', 'reimported_at', 'c_generated_at', 'c_completed_at', 'c_reimported_at', 'admin_id', 'g_admin_id', 'c_admin_id', 'icc_admin_id', 'cc_admin_id', 'r_admin_id', 'idcard_file', 'certificate_file', 'log_status'
    ];
}