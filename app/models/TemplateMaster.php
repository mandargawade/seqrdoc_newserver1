<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class TemplateMaster extends Model
{
    protected $table = 'template_master';

    protected $fillable = [
        'template_name','actual_template_name','template_desc', 'bg_template_id','background_template_status', 'template_size','width','height','unique_serial_no','lock_element','created_by','updated_by','status','create_refresh','is_block_chain','bc_document_description','bc_document_type'
    ];

	public function getTemplateNameAttribute($template_name)
	{
		$get_template_name = str_replace(' ', '',$template_name);
	    return $get_template_name;
	}
}
