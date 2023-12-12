<?php

namespace App\models\Superadmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class SuperFontMaster extends model
{
    use SoftDeletes;
    protected $connection = 'mysql';
    protected $table='super_font_master';
    protected $primaryKey = 'id';
    protected $fillable=['font_name','font_filename_N','font_filename_B','font_filename_I','font_filename_BI','created_by','updated_by','status','publish'];

	protected $softDelete = true;
}
