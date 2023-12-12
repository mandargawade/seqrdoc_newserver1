<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class FieldMaster extends Model
{
    protected $table = 'fields_master';

    protected $fillable = [
    	'template_id','name','mapped_name','security_type','field_position','text_justification','x_pos','y_pos','width','height','font_style','font_id','font_size','font_color','is_font_case','sample_text','sample_image','angle','font_color_extra','line_gap','length','uv_percentage','lock_index','is_repeat','infinite_height','include_image','grey_scale','is_uv_image','is_transparent_image','text_opicity','combo_qr_text','is_mapped','created_by','updated_by','is_meta_data','meta_data_label','meta_data_value'
    ];
}
