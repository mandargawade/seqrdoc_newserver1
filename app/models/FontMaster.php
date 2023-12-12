<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class FontMaster extends model
{
    use SoftDeletes;
    protected $table='font_master';
    protected $primaryKey = 'id';
    protected $fillable=['font_name','font_filename_N','font_filename_B','font_filename_I','font_filename_BI','created_by','updated_by','status','publish'];

	protected $softDelete = true;
}
